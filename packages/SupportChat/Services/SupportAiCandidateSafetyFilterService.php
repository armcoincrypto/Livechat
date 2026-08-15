<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAiConversationOutcome;
use App\Models\SupportAiLearningCandidate;
use App\Models\SupportAiLearningEvent;
use App\Models\SupportAiSuggestionUsage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deterministic quarantine filter for learning candidates before promotion.
 * Never deletes candidates; never calls OpenAI.
 */
final class SupportAiCandidateSafetyFilterService
{
    public const STATUS_ELIGIBLE = 'eligible';

    public const STATUS_QUARANTINED = 'quarantined';

    /** @var array<string, string> */
    private const UNSAFE_WORDING_PATTERNS = [
        'guaranteed' => '/\b(?:guaranteed|guarantee|100\s*%|definitely|no risk)\b/iu',
        'fake_eta_promise' => '/\b(?:exactly in \d+\s*minutes?|will be completed soon)\b/iu',
        'payment_confirmed_claim' => '/\b(?:payment is confirmed|payment has been sent|transaction is confirmed)\b/iu',
        'funds_safe_claim' => '/\b(?:funds are safe|funds?\s+(?:are\s+)?safe)\b/iu',
        'already_sent_claim' => '/\b(?:we already sent|we already processed your order)\b/iu',
        'exchange_completed_claim' => '/\b(?:your exchange is completed|exchange is completed)\b/iu',
    ];

    /** @var array<string, string> */
    private const REASON_SEVERITY = [
        'ignored_suggestion' => 'medium',
        'unknown_suggestion' => 'medium',
        'failed_outcome' => 'high',
        'reopened_outcome' => 'high',
        'pending_conversation_with_ignored' => 'medium',
        'learning_event_safety_flags' => 'high',
        'candidate_high_risk' => 'high',
        'unsafe_wording' => 'high',
        'duplicate_event_fingerprint' => 'high',
    ];

    public function __construct(
        private readonly SupportAiLearningService $learning,
        private readonly SupportAiLearningEvaluationService $evaluation,
    ) {}

    public function isEnabled(): bool
    {
        return filter_var(
            config('support_chat.ai.candidate_filtering.enabled', true),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    /**
     * @return array{
     *     eligible: bool,
     *     status: string,
     *     reasons: list<string>,
     *     severity: 'low'|'medium'|'high'
     * }
     */
    public function inspect(SupportAiLearningCandidate $candidate): array
    {
        if (! $this->isEnabled()) {
            return $this->eligibleResult();
        }

        $reasons = [];

        $event = $this->resolveLinkedLearningEvent($candidate);
        $usage = $this->resolveLinkedUsage($candidate, $event);
        $outcome = $this->resolveLinkedOutcome($candidate, $event);

        if (filter_var(config('support_chat.ai.candidate_filtering.block_ignored_usage', true), FILTER_VALIDATE_BOOLEAN)
            && $usage !== null
            && $usage->decision === SupportAiSuggestionUsage::DECISION_IGNORED) {
            $reasons[] = 'ignored_suggestion';
        }

        if (filter_var(config('support_chat.ai.candidate_filtering.block_unknown_usage', true), FILTER_VALIDATE_BOOLEAN)
            && $usage !== null
            && $usage->decision === SupportAiSuggestionUsage::DECISION_UNKNOWN) {
            $reasons[] = 'unknown_suggestion';
        }

        if (filter_var(config('support_chat.ai.candidate_filtering.block_failed_outcome', true), FILTER_VALIDATE_BOOLEAN)
            && $outcome !== null
            && $outcome->outcome === SupportAiConversationOutcome::OUTCOME_FAILED) {
            $reasons[] = 'failed_outcome';
        }

        if (filter_var(config('support_chat.ai.candidate_filtering.block_reopened_outcome', true), FILTER_VALIDATE_BOOLEAN)
            && $outcome !== null
            && $outcome->outcome === SupportAiConversationOutcome::OUTCOME_REOPENED) {
            $reasons[] = 'reopened_outcome';
        }

        if ($outcome !== null
            && $outcome->outcome === SupportAiConversationOutcome::OUTCOME_PENDING
            && $usage !== null
            && $usage->decision === SupportAiSuggestionUsage::DECISION_IGNORED) {
            $reasons[] = 'pending_conversation_with_ignored';
        }

        if ($event !== null) {
            $eventFlags = is_array($event->safety_flags) ? $event->safety_flags : [];
            if ($eventFlags !== []) {
                $reasons[] = 'learning_event_safety_flags';
            }

            if ($this->hasDuplicateEventFingerprint($event)) {
                $reasons[] = 'duplicate_event_fingerprint';
            }
        }

        if (strtolower(trim((string) ($candidate->risk_level ?? ''))) === 'high') {
            $reasons[] = 'candidate_high_risk';
        }

        $unsafeFlags = $this->detectUnsafeWording($this->candidateInspectionText($candidate));
        if ($unsafeFlags !== []) {
            $reasons[] = 'unsafe_wording';
        }

        $reasons = array_values(array_unique($reasons));

        if ($reasons === []) {
            return $this->eligibleResult();
        }

        return [
            'eligible' => false,
            'status' => self::STATUS_QUARANTINED,
            'reasons' => $reasons,
            'severity' => $this->resolveSeverity($reasons),
        ];
    }

    public function isQuarantined(SupportAiLearningCandidate $candidate): bool
    {
        $stored = $this->getStoredFilter($candidate);
        if ($stored !== null && ($stored['status'] ?? '') === self::STATUS_QUARANTINED) {
            return true;
        }

        if (! $this->isEnabled()) {
            return false;
        }

        return ! $this->inspect($candidate)['eligible'];
    }

    public function isPromotable(SupportAiLearningCandidate $candidate): bool
    {
        return ! $this->isQuarantined($candidate);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStoredFilter(SupportAiLearningCandidate $candidate): ?array
    {
        $evidence = is_array($candidate->evidence) ? $candidate->evidence : [];
        $filter = $evidence['safety_filter'] ?? null;

        return is_array($filter) ? $filter : null;
    }

    /**
     * @return array{
     *     eligible: bool,
     *     status: string,
     *     reasons: list<string>,
     *     severity: 'low'|'medium'|'high',
     *     candidate_id: int,
     *     persisted: bool
     * }
     */
    public function applyToCandidate(SupportAiLearningCandidate $candidate, bool $persist = true): array
    {
        $result = $this->inspect($candidate);
        $payload = array_merge($result, [
            'candidate_id' => (int) $candidate->id,
            'persisted' => false,
        ]);

        if (! $persist) {
            return $payload;
        }

        $evidence = is_array($candidate->evidence) ? $candidate->evidence : [];
        $evidence['safety_filter'] = [
            'status' => $result['status'],
            'reasons' => $result['reasons'],
            'severity' => $result['severity'],
            'checked_at' => now()->toIso8601String(),
        ];
        $candidate->evidence = $evidence;

        if (! $result['eligible'] && $result['severity'] === 'high') {
            $candidate->risk_level = 'high';
        }

        $candidate->save();
        $payload['persisted'] = true;

        return $payload;
    }

    /**
     * @return array{
     *     total: int,
     *     eligible: int,
     *     quarantined: int,
     *     reason_counts: array<string, int>,
     *     severity_counts: array<string, int>,
     *     linked_ignored: int,
     *     linked_unknown: int,
     *     linked_failed_reopened: int,
     *     blocked_unsafe_wording: int,
     *     dry_run: bool,
     *     candidates: list<array<string, mixed>>
     * }
     */
    public function filterCandidatesInWindow(int $days = 30, bool $dryRun = false): array
    {
        $days = max(1, min(365, $days));
        $since = Carbon::now()->subDays($days);

        $candidates = SupportAiLearningCandidate::query()
            ->where('created_at', '>=', $since)
            ->orderBy('id')
            ->get();

        $eligible = 0;
        $quarantined = 0;
        $reasonCounts = [];
        $severityCounts = ['low' => 0, 'medium' => 0, 'high' => 0];
        $linkedIgnored = 0;
        $linkedUnknown = 0;
        $linkedFailedReopened = 0;
        $blockedUnsafeWording = 0;
        $rows = [];

        foreach ($candidates as $candidate) {
            $result = $this->applyToCandidate($candidate, ! $dryRun);

            if ($result['eligible']) {
                $eligible++;
            } else {
                $quarantined++;
            }

            foreach ($result['reasons'] as $reason) {
                $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;
            }

            if (! $result['eligible']) {
                $severity = (string) ($result['severity'] ?? 'medium');
                if (isset($severityCounts[$severity])) {
                    $severityCounts[$severity]++;
                }
            }

            if (in_array('ignored_suggestion', $result['reasons'], true)
                || in_array('pending_conversation_with_ignored', $result['reasons'], true)) {
                $linkedIgnored++;
            }
            if (in_array('unknown_suggestion', $result['reasons'], true)) {
                $linkedUnknown++;
            }
            if (in_array('failed_outcome', $result['reasons'], true)
                || in_array('reopened_outcome', $result['reasons'], true)) {
                $linkedFailedReopened++;
            }
            if (in_array('unsafe_wording', $result['reasons'], true)) {
                $blockedUnsafeWording++;
            }

            $rows[] = [
                'id' => (int) $candidate->id,
                'type' => (string) $candidate->candidate_type,
                'intent' => $candidate->intent,
                'status' => $result['status'],
                'severity' => $result['severity'],
                'reasons' => $result['reasons'],
            ];
        }

        return [
            'total' => $candidates->count(),
            'eligible' => $eligible,
            'quarantined' => $quarantined,
            'reason_counts' => $reasonCounts,
            'severity_counts' => $severityCounts,
            'linked_ignored' => $linkedIgnored,
            'linked_unknown' => $linkedUnknown,
            'linked_failed_reopened' => $linkedFailedReopened,
            'blocked_unsafe_wording' => $blockedUnsafeWording,
            'dry_run' => $dryRun,
            'candidates' => $rows,
        ];
    }

    /**
     * @return array{eligible: bool, status: string, reasons: list<string>, severity: 'low'}
     */
    private function eligibleResult(): array
    {
        return [
            'eligible' => true,
            'status' => self::STATUS_ELIGIBLE,
            'reasons' => [],
            'severity' => 'low',
        ];
    }

    public function resolveLinkedLearningEvent(SupportAiLearningCandidate $candidate): ?SupportAiLearningEvent
    {
        $evidence = is_array($candidate->evidence) ? $candidate->evidence : [];
        $eventId = isset($evidence['learning_event_id']) ? (int) $evidence['learning_event_id'] : 0;

        if ($eventId < 1) {
            return null;
        }

        return SupportAiLearningEvent::query()->find($eventId);
    }

    public function resolveLinkedUsage(
        SupportAiLearningCandidate $candidate,
        ?SupportAiLearningEvent $event,
    ): ?SupportAiSuggestionUsage {
        if (! Schema::hasTable('support_ai_suggestion_usages')) {
            return null;
        }

        if ($event !== null) {
            $byEvent = SupportAiSuggestionUsage::query()
                ->where('learning_event_id', (int) $event->id)
                ->orderByDesc('id')
                ->first();

            if ($byEvent !== null) {
                return $byEvent;
            }

            if ($event->conversation_id !== null && $event->message_id !== null) {
                $byAnchor = SupportAiSuggestionUsage::query()
                    ->where('conversation_id', (int) $event->conversation_id)
                    ->where('visitor_message_id', (int) $event->message_id)
                    ->orderByDesc('id')
                    ->first();

                if ($byAnchor !== null) {
                    return $byAnchor;
                }
            }
        }

        return null;
    }

    private function resolveLinkedOutcome(
        SupportAiLearningCandidate $candidate,
        ?SupportAiLearningEvent $event,
    ): ?SupportAiConversationOutcome {
        if (! Schema::hasTable('support_ai_conversation_outcomes')) {
            return null;
        }

        $conversationId = $event?->conversation_id;
        if ($conversationId === null || (int) $conversationId < 1) {
            return null;
        }

        return SupportAiConversationOutcome::query()
            ->where('conversation_id', (int) $conversationId)
            ->orderByDesc('id')
            ->first();
    }

    private function hasDuplicateEventFingerprint(SupportAiLearningEvent $event): bool
    {
        $metadata = is_array($event->metadata) ? $event->metadata : [];
        $fingerprint = trim((string) ($metadata['event_fingerprint'] ?? ''));
        if ($fingerprint === '') {
            return false;
        }

        if (! Schema::hasTable('support_ai_learning_events')) {
            return false;
        }

        $count = DB::table('support_ai_learning_events')
            ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.event_fingerprint")) = ?', [$fingerprint])
            ->count();

        return $count > 1;
    }

    /**
     * @return list<string>
     */
    private function detectUnsafeWording(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        $flags = [];
        foreach (self::UNSAFE_WORDING_PATTERNS as $flag => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $flags[] = $flag;
            }
        }

        $evalFlags = $this->evaluation->detectEvaluationFlags($text);
        foreach ($evalFlags as $flag) {
            $flags[] = (string) $flag;
        }

        $learningFlags = $this->learning->detectSafetyFlags($text);
        foreach ($learningFlags as $flag) {
            $flags[] = (string) $flag;
        }

        return array_values(array_unique($flags));
    }

    private function candidateInspectionText(SupportAiLearningCandidate $candidate): string
    {
        $parts = array_filter([
            (string) ($candidate->proposed_example ?? ''),
            (string) ($candidate->after_example ?? ''),
            (string) ($candidate->proposed_rule ?? ''),
            (string) ($candidate->problem_summary ?? ''),
        ], static fn (string $part): bool => trim($part) !== '');

        return $this->learning->sanitizeLearningText(implode("\n", $parts));
    }

    /**
     * @param  list<string>  $reasons
     * @return 'low'|'medium'|'high'
     */
    private function resolveSeverity(array $reasons): string
    {
        $severity = 'low';
        foreach ($reasons as $reason) {
            $mapped = self::REASON_SEVERITY[$reason] ?? 'medium';
            if ($mapped === 'high') {
                return 'high';
            }
            if ($mapped === 'medium') {
                $severity = 'medium';
            }
        }

        return $severity;
    }
}
