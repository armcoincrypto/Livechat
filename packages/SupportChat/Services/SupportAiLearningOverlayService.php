<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAiLearningCandidate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Builds optional runtime-only learning overlay context from staged/approved candidates.
 * Never modifies production prompts, playbook code, or draft service rules.
 */
final class SupportAiLearningOverlayService
{
    /** @var list<string> */
    private const HARD_FAIL_FLAGS = [
        'fake_eta',
        'fake_confirmation',
        'guarantee_claim',
        'funds_safe_claim',
        'recovery_guarantee',
        'asks_known_data_again',
        'unsupported_financial_claim',
        'secret_leak_risk',
        'too_long',
        'wrong_language',
    ];

    /** @var list<string> */
    private const ALLOWED_EVALUATION_RESULTS = [
        'staged',
        'approved',
    ];

    public function __construct(
        private readonly SupportAiLearningEvaluationService $evaluation,
        private readonly SupportAiLearningService $learning,
        private readonly SupportAiLearningPreviewService $preview,
    ) {}

    public function isEnabled(): bool
    {
        if (! filter_var(config('support_chat.ai.learning_overlay.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        try {
            return Schema::hasTable('support_ai_learning_candidates');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{
     *     context: string,
     *     meta: array<string, mixed>,
     *     stats: array<string, mixed>
     * }
     */
    public function buildOverlayPackage(?string $intent = null, ?string $language = null): array
    {
        $stats = $this->buildPreviewStats($intent, $language);

        if (! $this->isEnabled()) {
            return [
                'context' => '',
                'meta' => [],
                'stats' => $stats,
            ];
        }

        try {
            $eligible = $this->loadEligibleCandidates($intent, $language);
            $included = $this->selectCandidatesForOverlay($eligible, $intent, $language);
            $context = $this->buildOverlayContext($included, $intent, $language);
            $meta = $this->recordOverlayMetadata($included, $context !== '');

            $stats = $this->buildPreviewStats($intent, $language, $eligible, $included, $context);

            return [
                'context' => $context,
                'meta' => $meta,
                'stats' => $stats,
            ];
        } catch (Throwable $e) {
            Log::warning('support-chat ai:learning_overlay_failed', [
                'stage' => 'build_overlay_package',
                'intent' => $intent,
                'language' => $language,
                'exception' => SupportChatDiagnosticsLog::sanitizeError($e->getMessage()),
            ]);

            return [
                'context' => '',
                'meta' => [],
                'stats' => $this->buildPreviewStats($intent, $language),
            ];
        }
    }

    /**
     * @return Collection<int, SupportAiLearningCandidate>
     */
    public function loadEligibleCandidates(?string $intent = null, ?string $language = null): Collection
    {
        if (! Schema::hasTable('support_ai_learning_candidates')) {
            return collect();
        }

        $statuses = $this->allowedStatuses();

        $query = SupportAiLearningCandidate::query()
            ->whereIn('status', $statuses)
            ->orderByDesc('evaluation_score')
            ->orderBy('intent')
            ->orderBy('id');

        if ($intent !== null && trim($intent) !== '') {
            $query->where(function ($builder) use ($intent): void {
                $builder->where('intent', $intent)->orWhereNull('intent')->orWhere('intent', '');
            });
        }

        return $query->get()->filter(function (SupportAiLearningCandidate $candidate) use ($language): bool {
            return $this->isCandidateEligible($candidate, $language);
        })->values();
    }

    /**
     * @param  Collection<int, SupportAiLearningCandidate>  $candidates
     * @return array<string, Collection<int, SupportAiLearningCandidate>>
     */
    public function groupCandidatesByIntent(Collection $candidates): array
    {
        $grouped = [];
        foreach ($candidates as $candidate) {
            $intent = trim((string) ($candidate->intent ?? ''));
            if ($intent === '') {
                $intent = 'unknown_context';
            }
            if (! isset($grouped[$intent])) {
                $grouped[$intent] = collect();
            }
            $grouped[$intent]->push($candidate);
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @param  Collection<int, SupportAiLearningCandidate>  $candidates
     */
    public function buildOverlayContext(Collection $candidates, ?string $intent = null, ?string $language = null): string
    {
        if ($candidates->isEmpty()) {
            return '';
        }

        $lines = [];
        $lines[] = 'Runtime learning overlay:';
        $lines[] = '- These are approved/staged operator-learned examples.';
        $lines[] = '- Use them only if relevant to the visitor intent/language.';
        $lines[] = '- They do not override safety rules.';
        $lines[] = '- Never use unsafe claims.';
        $lines[] = '';
        $lines[] = 'Learning overlay rules:';
        $lines[] = '- Use only if relevant.';
        $lines[] = '- Safety rules override overlay examples.';
        $lines[] = '- Do not invent status, payment confirmation, ETA, guarantees.';
        $lines[] = '- Do not ask for data already provided.';
        $lines[] = '- Keep replies concise and operator-style.';

        $grouped = $this->groupCandidatesByIntent($candidates);
        if ($intent !== null && trim($intent) !== '' && isset($grouped[$intent])) {
            $lines[] = '';
            $lines[] = 'Priority intent: '.$intent;
            $lines[] = $this->formatCandidateGroup($grouped[$intent], $language);
            unset($grouped[$intent]);
        }

        foreach ($grouped as $groupIntent => $groupCandidates) {
            $lines[] = '';
            $lines[] = 'Intent: '.$groupIntent;
            $lines[] = $this->formatCandidateGroup($groupCandidates, $language);
        }

        $text = $this->sanitizeOverlayText(implode("\n", $lines));

        return $this->enforceMaxChars($text);
    }

    public function sanitizeOverlayText(string $text): string
    {
        return $this->learning->sanitizeLearningText($text);
    }

    public function enforceMaxChars(string $text): string
    {
        $max = max(500, min(10000, (int) config('support_chat.ai.learning_overlay.max_chars', 3000)));
        if (mb_strlen($text, 'UTF-8') <= $max) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $max, 'UTF-8');
        $lastBreak = mb_strrpos($truncated, "\n", 0, 'UTF-8');
        if ($lastBreak !== false && $lastBreak > (int) ($max * 0.6)) {
            $truncated = mb_substr($truncated, 0, $lastBreak, 'UTF-8');
        }

        return rtrim($truncated)."\n[overlay truncated]";
    }

    /**
     * @param  Collection<int, SupportAiLearningCandidate>  $included
     * @return array<string, mixed>
     */
    public function recordOverlayMetadata(Collection $included, bool $used): array
    {
        if (! $used || $included->isEmpty()) {
            return [];
        }

        $ids = $included->pluck('id')->map(static fn ($id): int => (int) $id)->values()->all();

        return [
            'overlay_enabled' => true,
            'overlay_candidate_ids' => $ids,
            'overlay_candidate_count' => count($ids),
        ];
    }

    public function isCandidateEligible(SupportAiLearningCandidate $candidate, ?string $language = null): bool
    {
        if ($this->preview->validateCandidateForPreview($candidate) !== null) {
            return false;
        }

        if (! in_array($candidate->status, $this->allowedStatuses(), true)) {
            return false;
        }

        if ($candidate->status === SupportAiLearningCandidate::STATUS_REJECTED
            || $candidate->status === SupportAiLearningCandidate::STATUS_PENDING) {
            return false;
        }

        if ($this->statusRank((string) $candidate->status) < $this->statusRank($this->minStatus())) {
            return false;
        }

        $evaluationResult = trim((string) ($candidate->evaluation_result ?? ''));
        if ($evaluationResult !== '' && ! in_array($evaluationResult, self::ALLOWED_EVALUATION_RESULTS, true)) {
            return false;
        }

        if ($evaluationResult === '' && $candidate->status !== SupportAiLearningCandidate::STATUS_APPROVED) {
            return false;
        }

        if (! in_array((string) $candidate->candidate_type, $this->allowedTypes(), true)) {
            return false;
        }

        if (strtolower(trim((string) ($candidate->risk_level ?? ''))) === 'high') {
            return false;
        }

        if (! $this->hasProposedContent($candidate)) {
            return false;
        }

        if (! $this->hasPerfectSafetyScore($candidate)) {
            return false;
        }

        if (! $this->matchesLanguage($candidate, $language)) {
            return false;
        }

        return true;
    }

    /**
     * @param  Collection<int, SupportAiLearningCandidate>|null  $eligible
     * @param  Collection<int, SupportAiLearningCandidate>|null  $included
     * @return array<string, mixed>
     */
    public function buildPreviewStats(
        ?string $intent = null,
        ?string $language = null,
        ?Collection $eligible = null,
        ?Collection $included = null,
        ?string $context = null,
    ): array {
        $eligible ??= $this->isEnabled() ? $this->loadEligibleCandidates($intent, $language) : collect();
        $included ??= $this->selectCandidatesForOverlay($eligible, $intent, $language);
        $context ??= $included->isEmpty() ? '' : $this->buildOverlayContext($included, $intent, $language);

        $intentCounts = [];
        foreach ($included as $candidate) {
            $key = trim((string) ($candidate->intent ?? ''));
            if ($key === '') {
                $key = 'unknown_context';
            }
            $intentCounts[$key] = ($intentCounts[$key] ?? 0) + 1;
        }
        ksort($intentCounts);

        return [
            'overlay_enabled' => $this->isEnabled(),
            'eligible_candidates' => $eligible->count(),
            'included_candidates' => $included->count(),
            'total_chars' => mb_strlen($context, 'UTF-8'),
            'intents' => $intentCounts,
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedStatuses(): array
    {
        $min = $this->minStatus();
        if ($min === SupportAiLearningCandidate::STATUS_STAGED) {
            return [
                SupportAiLearningCandidate::STATUS_STAGED,
                SupportAiLearningCandidate::STATUS_APPROVED,
            ];
        }

        return [SupportAiLearningCandidate::STATUS_APPROVED];
    }

    private function minStatus(): string
    {
        $configured = strtolower(trim((string) config('support_chat.ai.learning_overlay.min_status', 'approved')));

        return $configured === SupportAiLearningCandidate::STATUS_STAGED
            ? SupportAiLearningCandidate::STATUS_STAGED
            : SupportAiLearningCandidate::STATUS_APPROVED;
    }

    /**
     * @return list<string>
     */
    private function allowedTypes(): array
    {
        $configured = config('support_chat.ai.learning_overlay.allowed_types', []);
        if (! is_array($configured) || $configured === []) {
            return [
                SupportAiLearningCandidate::TYPE_PLAYBOOK_EXAMPLE,
                SupportAiLearningCandidate::TYPE_TONE_RULE,
                SupportAiLearningCandidate::TYPE_SAFETY_RULE,
                SupportAiLearningCandidate::TYPE_INTENT_RULE,
                SupportAiLearningCandidate::TYPE_FOLLOWUP_RULE,
                SupportAiLearningCandidate::TYPE_OPERATOR_ACTION_RULE,
                SupportAiLearningCandidate::TYPE_EDGE_CASE_RULE,
            ];
        }

        return array_values(array_filter(array_map(static fn ($type): string => trim((string) $type), $configured)));
    }

    private function statusRank(string $status): int
    {
        return match (strtolower(trim($status))) {
            SupportAiLearningCandidate::STATUS_APPROVED => 2,
            SupportAiLearningCandidate::STATUS_STAGED => 1,
            default => 0,
        };
    }

    private function hasProposedContent(SupportAiLearningCandidate $candidate): bool
    {
        $example = trim((string) ($candidate->proposed_example ?? ''));
        $rule = trim((string) ($candidate->proposed_rule ?? ''));

        return $example !== '' || $rule !== '';
    }

    private function hasPerfectSafetyScore(SupportAiLearningCandidate $candidate): bool
    {
        $evalFlags = is_array($candidate->evaluation_flags) ? $candidate->evaluation_flags : [];
        if (! empty($evalFlags['hard_fail'])) {
            return false;
        }

        $storedFlags = is_array($evalFlags['flags'] ?? null) ? $evalFlags['flags'] : [];
        foreach ($storedFlags as $flag) {
            if (in_array((string) $flag, self::HARD_FAIL_FLAGS, true)) {
                return false;
            }
        }

        $safetyScore = $evalFlags['safety_score'] ?? null;
        if ($safetyScore !== null && (float) $safetyScore < 100.0) {
            return false;
        }

        return true;
    }

    private function matchesLanguage(SupportAiLearningCandidate $candidate, ?string $language): bool
    {
        $candidateLanguage = strtolower(trim((string) ($candidate->language ?? '')));
        if ($candidateLanguage === '') {
            return true;
        }

        $target = strtolower(trim((string) ($language ?? '')));
        if ($target === '') {
            return true;
        }

        return $candidateLanguage === $target
            || str_starts_with($target, $candidateLanguage)
            || str_starts_with($candidateLanguage, $target);
    }

    /**
     * @param  Collection<int, SupportAiLearningCandidate>  $eligible
     * @return Collection<int, SupportAiLearningCandidate>
     */
    private function selectCandidatesForOverlay(
        Collection $eligible,
        ?string $intent,
        ?string $language,
    ): Collection {
        $max = max(1, min(50, (int) config('support_chat.ai.learning_overlay.max_candidates', 12)));

        $sorted = $eligible->sortByDesc(function (SupportAiLearningCandidate $candidate) use ($intent, $language): int {
            $score = (int) round((float) ($candidate->evaluation_score ?? 0));
            if ($intent !== null && trim($intent) !== '' && trim((string) ($candidate->intent ?? '')) === $intent) {
                $score += 1000;
            }
            if ($this->matchesLanguage($candidate, $language)) {
                $score += 100;
            }
            if ($candidate->status === SupportAiLearningCandidate::STATUS_APPROVED) {
                $score += 10;
            }

            return $score;
        })->values();

        return $sorted->take($max)->values();
    }

    /**
     * @param  Collection<int, SupportAiLearningCandidate>  $candidates
     */
    private function formatCandidateGroup(Collection $candidates, ?string $language): string
    {
        $lines = [];
        foreach ($candidates as $candidate) {
            $type = (string) $candidate->candidate_type;
            $rule = $this->sanitizeOverlayText(trim((string) ($candidate->proposed_rule ?? '')));
            $example = $this->sanitizeOverlayText(trim((string) ($candidate->proposed_example ?? '')));
            if ($example === '') {
                $example = $this->sanitizeOverlayText(trim((string) ($candidate->after_example ?? '')));
            }

            $lines[] = '- ['.$type.' #'.(int) $candidate->id.']';
            if ($rule !== '') {
                $lines[] = '  Rule: '.$rule;
            }
            if ($example !== '') {
                $lines[] = '  Example: '.$example;
            }
            if ($candidate->language !== null && trim((string) $candidate->language) !== '') {
                $lines[] = '  Language: '.trim((string) $candidate->language);
            }
        }

        return implode("\n", $lines);
    }
}
