<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAiLearningCandidate;
use App\Models\SupportAiLearningEvent;
use App\Models\SupportAiSuggestionUsage;
use Illuminate\Support\Facades\Schema;

/**
 * Applies gated status transitions after evaluation. Never modifies live prompts/playbook.
 */
final class SupportAiLearningPromotionService
{
    private const STAGE_OVERALL_MIN = 75.0;

    private const APPROVE_OVERALL_MIN = 90.0;

    private const APPROVE_OPERATOR_FIT_MIN = 80.0;

    public function __construct(
        private readonly SupportAiLearningEvaluationService $evaluation,
        private readonly SupportAiCandidateSafetyFilterService $safetyFilter,
    ) {}

    public function isPromotionThresholdEnabled(): bool
    {
        return filter_var(
            config('support_chat.ai.promotion_thresholds.enabled', true),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    public function isPromotable(SupportAiLearningCandidate $candidate): bool
    {
        if ($this->safetyFilter->isQuarantined($candidate)) {
            return false;
        }

        return $this->passesAcceptedUsageThreshold($candidate)['passes'];
    }

    /**
     * @return array{
     *     passes: bool,
     *     accepted_exact_count: int,
     *     accepted_modified_count: int,
     *     accepted_total: int,
     *     required_min_accepted_samples: int,
     *     reason: string
     * }
     */
    public function passesAcceptedUsageThreshold(SupportAiLearningCandidate $candidate): array
    {
        $requiredMin = max(1, min(50, (int) config('support_chat.ai.promotion_thresholds.min_accepted_samples', 3)));
        $allowUnlinked = filter_var(
            config('support_chat.ai.promotion_thresholds.allow_unlinked_candidates', false),
            FILTER_VALIDATE_BOOLEAN,
        );

        $base = [
            'accepted_exact_count' => 0,
            'accepted_modified_count' => 0,
            'accepted_total' => 0,
            'required_min_accepted_samples' => $requiredMin,
        ];

        if (! $this->isPromotionThresholdEnabled()) {
            return array_merge($base, [
                'passes' => true,
                'reason' => 'threshold_disabled',
            ]);
        }

        $evidence = is_array($candidate->evidence) ? $candidate->evidence : [];
        $eventId = isset($evidence['learning_event_id']) ? (int) $evidence['learning_event_id'] : 0;
        $event = $this->safetyFilter->resolveLinkedLearningEvent($candidate);
        $usage = $this->safetyFilter->resolveLinkedUsage($candidate, $event);

        if (($eventId < 1 || $event === null) && ! $allowUnlinked) {
            return array_merge($base, [
                'passes' => false,
                'reason' => 'unlinked_candidate_blocked',
            ]);
        }

        if ($usage === null && ! $allowUnlinked) {
            return array_merge($base, [
                'passes' => false,
                'reason' => 'unlinked_candidate_blocked',
            ]);
        }

        if ($usage !== null && in_array($usage->decision, [
            SupportAiSuggestionUsage::DECISION_IGNORED,
            SupportAiSuggestionUsage::DECISION_UNKNOWN,
        ], true)) {
            return array_merge($base, [
                'passes' => false,
                'reason' => 'accepted_threshold_not_met',
            ]);
        }

        $intent = trim((string) ($candidate->intent ?? $event?->intent ?? ''));
        $counts = $this->countAcceptedUsagesForIntent($intent);
        $acceptedTotal = $counts['exact'] + $counts['modified'];
        $passes = $acceptedTotal >= $requiredMin;

        return [
            'passes' => $passes,
            'accepted_exact_count' => $counts['exact'],
            'accepted_modified_count' => $counts['modified'],
            'accepted_total' => $acceptedTotal,
            'required_min_accepted_samples' => $requiredMin,
            'reason' => $passes ? 'accepted_threshold_passed' : 'accepted_threshold_not_met',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStoredPromotionThreshold(SupportAiLearningCandidate $candidate): ?array
    {
        $evidence = is_array($candidate->evidence) ? $candidate->evidence : [];
        $threshold = $evidence['promotion_threshold'] ?? null;

        return is_array($threshold) ? $threshold : null;
    }

    /**
     * @return array{
     *     passes: bool,
     *     accepted_exact_count: int,
     *     accepted_modified_count: int,
     *     accepted_total: int,
     *     required_min_accepted_samples: int,
     *     reason: string,
     *     persisted: bool
     * }
     */
    public function applyAcceptedUsageThreshold(SupportAiLearningCandidate $candidate, bool $persist = true): array
    {
        $result = $this->passesAcceptedUsageThreshold($candidate);
        $payload = array_merge($result, ['persisted' => false]);

        if (! $persist) {
            return $payload;
        }

        $evidence = is_array($candidate->evidence) ? $candidate->evidence : [];
        $evidence['promotion_threshold'] = [
            'passes' => $result['passes'],
            'accepted_exact_count' => $result['accepted_exact_count'],
            'accepted_modified_count' => $result['accepted_modified_count'],
            'accepted_total' => $result['accepted_total'],
            'required_min_accepted_samples' => $result['required_min_accepted_samples'],
            'reason' => $result['reason'],
            'checked_at' => now()->toIso8601String(),
        ];
        $candidate->evidence = $evidence;
        $candidate->save();
        $payload['persisted'] = true;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $evaluationResult
     */
    public function promoteIfSafe(
        SupportAiLearningCandidate $candidate,
        array $evaluationResult,
        bool $persist = true,
    ): array {
        $filterResult = $this->safetyFilter->applyToCandidate($candidate, $persist);

        if (! $filterResult['eligible']) {
            $evaluationResult['hard_fail'] = true;
            $evaluationResult['flags'] = array_values(array_unique(array_merge(
                is_array($evaluationResult['flags'] ?? null) ? $evaluationResult['flags'] : [],
                ['safety_filter_quarantined'],
            )));
            $evaluationResult['summary'] = sprintf(
                'Quarantined by safety filter (%s). %s',
                implode(', ', $filterResult['reasons']),
                (string) ($evaluationResult['summary'] ?? ''),
            );

            return $this->rejectCandidate($candidate, $evaluationResult, $persist);
        }

        $thresholdResult = $this->applyAcceptedUsageThreshold($candidate, $persist);

        if (! $thresholdResult['passes']) {
            $evaluationResult['hard_fail'] = true;
            $evaluationResult['flags'] = array_values(array_unique(array_merge(
                is_array($evaluationResult['flags'] ?? null) ? $evaluationResult['flags'] : [],
                ['accepted_threshold_blocked'],
            )));
            $evaluationResult['summary'] = sprintf(
                'Blocked by accepted usage threshold (%s, accepted=%d/%d). %s',
                $thresholdResult['reason'],
                $thresholdResult['accepted_total'],
                $thresholdResult['required_min_accepted_samples'],
                (string) ($evaluationResult['summary'] ?? ''),
            );

            return $this->rejectCandidate($candidate, $evaluationResult, $persist);
        }

        if ($evaluationResult['hard_fail'] ?? false) {
            return $this->rejectCandidate($candidate, $evaluationResult, $persist);
        }

        if ((float) ($evaluationResult['safety_score'] ?? 0) < 100.0) {
            return $this->rejectCandidate($candidate, $evaluationResult, $persist);
        }

        $overall = (float) ($evaluationResult['overall_score'] ?? 0);
        $operatorFit = (float) ($evaluationResult['operator_fit_score'] ?? 0);

        if ($overall >= self::APPROVE_OVERALL_MIN
            && $operatorFit >= self::APPROVE_OPERATOR_FIT_MIN
            && (float) ($evaluationResult['safety_score'] ?? 0) >= 100.0) {
            return $this->approveCandidateIfStrict($candidate, $evaluationResult, $persist);
        }

        if ($overall >= self::STAGE_OVERALL_MIN && (float) ($evaluationResult['safety_score'] ?? 0) >= 100.0) {
            return $this->stageCandidate($candidate, $evaluationResult, $persist);
        }

        return $this->rejectCandidate($candidate, $evaluationResult, $persist);
    }

    /**
     * @param  array<string, mixed>  $evaluationResult
     * @return array{action: string, status: string, evaluation: array<string, mixed>}
     */
    public function rejectCandidate(
        SupportAiLearningCandidate $candidate,
        array $evaluationResult,
        bool $persist = true,
    ): array {
        $evaluationResult['result'] = 'rejected';

        if ($persist) {
            $this->persistEvaluation($candidate, $evaluationResult, SupportAiLearningCandidate::STATUS_REJECTED);
            $candidate->rejected_at = now();
            $candidate->save();
        }

        return [
            'action' => 'rejected',
            'status' => SupportAiLearningCandidate::STATUS_REJECTED,
            'evaluation' => $evaluationResult,
        ];
    }

    /**
     * @param  array<string, mixed>  $evaluationResult
     * @return array{action: string, status: string, evaluation: array<string, mixed>}
     */
    public function stageCandidate(
        SupportAiLearningCandidate $candidate,
        array $evaluationResult,
        bool $persist = true,
    ): array {
        $evaluationResult['result'] = 'staged';

        if ($persist) {
            $this->persistEvaluation($candidate, $evaluationResult, SupportAiLearningCandidate::STATUS_STAGED);
            $candidate->auto_promoted_at = now();
            $candidate->save();
        }

        return [
            'action' => 'staged',
            'status' => SupportAiLearningCandidate::STATUS_STAGED,
            'evaluation' => $evaluationResult,
        ];
    }

    /**
     * @param  array<string, mixed>  $evaluationResult
     * @return array{action: string, status: string, evaluation: array<string, mixed>}
     */
    public function approveCandidateIfStrict(
        SupportAiLearningCandidate $candidate,
        array $evaluationResult,
        bool $persist = true,
    ): array {
        $evaluationResult['result'] = 'approved';

        if ($persist) {
            $this->persistEvaluation($candidate, $evaluationResult, SupportAiLearningCandidate::STATUS_APPROVED);
            $candidate->approved_at = now();
            $candidate->auto_promoted_at = now();
            $candidate->save();
        }

        return [
            'action' => 'approved',
            'status' => SupportAiLearningCandidate::STATUS_APPROVED,
            'evaluation' => $evaluationResult,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{
     *     evaluated: int,
     *     staged: int,
     *     approved: int,
     *     rejected: int,
     *     candidates: list<array<string, mixed>>
     * }
     */
    public function evaluateAndPromotePending(int $limit = 50, bool $dryRun = false, array $options = []): array
    {
        $evaluations = $this->evaluation->evaluatePendingCandidates($limit, $options);

        $staged = 0;
        $approved = 0;
        $rejected = 0;
        $rows = [];

        foreach ($evaluations as $eval) {
            $candidate = SupportAiLearningCandidate::query()->find($eval['candidate_id']);
            if ($candidate === null) {
                continue;
            }

            $outcome = $this->promoteIfSafe($candidate, $eval, ! $dryRun);
            $action = $outcome['action'];
            if ($action === 'staged') {
                $staged++;
            } elseif ($action === 'approved') {
                $approved++;
            } else {
                $rejected++;
            }

            $rows[] = [
                'id' => (int) $candidate->id,
                'type' => $eval['type'],
                'intent' => $eval['intent'],
                'overall_score' => $eval['overall_score'],
                'result' => $outcome['status'],
                'flags' => $eval['flags'],
            ];
        }

        return [
            'evaluated' => count($rows),
            'staged' => $staged,
            'approved' => $approved,
            'rejected' => $rejected,
            'candidates' => $rows,
        ];
    }

    /**
     * @return array{exact: int, modified: int}
     */
    private function countAcceptedUsagesForIntent(string $intent): array
    {
        if (! Schema::hasTable('support_ai_suggestion_usages') || $intent === '') {
            return ['exact' => 0, 'modified' => 0];
        }

        $events = SupportAiLearningEvent::query()
            ->where('intent', $intent)
            ->get(['id', 'conversation_id', 'message_id']);

        if ($events->isEmpty()) {
            return ['exact' => 0, 'modified' => 0];
        }

        $eventIds = $events->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        $countDecision = static function (string $decision) use ($events, $eventIds): int {
            return SupportAiSuggestionUsage::query()
                ->where('decision', $decision)
                ->where(function ($query) use ($events, $eventIds): void {
                    $query->whereIn('learning_event_id', $eventIds);

                    foreach ($events as $event) {
                        if ($event->conversation_id === null || $event->message_id === null) {
                            continue;
                        }

                        $query->orWhere(static function ($inner) use ($event): void {
                            $inner->where('conversation_id', (int) $event->conversation_id)
                                ->where('visitor_message_id', (int) $event->message_id);
                        });
                    }
                })
                ->count();
        };

        return [
            'exact' => $countDecision(SupportAiSuggestionUsage::DECISION_ACCEPTED_EXACT),
            'modified' => $countDecision(SupportAiSuggestionUsage::DECISION_ACCEPTED_MODIFIED),
        ];
    }

    /**
     * @param  array<string, mixed>  $evaluationResult
     */
    private function persistEvaluation(
        SupportAiLearningCandidate $candidate,
        array $evaluationResult,
        string $status,
    ): void {
        $candidate->status = $status;
        $candidate->evaluation_score = $evaluationResult['overall_score'];
        $candidate->evaluation_result = (string) ($evaluationResult['result'] ?? $status);
        $candidate->evaluation_summary = (string) ($evaluationResult['summary'] ?? '');
        $candidate->evaluation_flags = [
            'safety_score' => $evaluationResult['safety_score'] ?? null,
            'relevance_score' => $evaluationResult['relevance_score'] ?? null,
            'operator_fit_score' => $evaluationResult['operator_fit_score'] ?? null,
            'conciseness_score' => $evaluationResult['conciseness_score'] ?? null,
            'language_score' => $evaluationResult['language_score'] ?? null,
            'overall_score' => $evaluationResult['overall_score'] ?? null,
            'hard_fail' => $evaluationResult['hard_fail'] ?? false,
            'flags' => $evaluationResult['flags'] ?? [],
        ];
        $candidate->evaluated_at = now();
        $candidate->save();
    }
}
