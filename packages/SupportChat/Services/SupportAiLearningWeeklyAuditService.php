<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAiConversationOutcome;
use App\Models\SupportAiLearningCandidate;
use App\Models\SupportAiSuggestionUsage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only Phase A weekly audit snapshot. No learning side-effects.
 */
final class SupportAiLearningWeeklyAuditService
{
    public function isEnabled(): bool
    {
        return filter_var(
            config('support_chat.ai.weekly_audit.enabled', true),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    public function isTelemetryAvailable(): bool
    {
        return Schema::hasTable('support_ai_suggestion_usages')
            && Schema::hasTable('support_ai_conversation_outcomes');
    }

    /**
     * @return array<string, mixed>
     */
    public function buildReport(?int $days = null): array
    {
        $days = max(1, min(365, $days ?? (int) config('support_chat.ai.weekly_audit.lookback_days', 7)));
        $since = Carbon::now()->subDays($days);
        $milestoneMinAccepted = max(1, (int) config('support_chat.ai.weekly_audit.milestone_min_accepted', 10));
        $milestoneMinResolved = max(1, (int) config('support_chat.ai.weekly_audit.milestone_min_resolved', 10));

        if (! $this->isTelemetryAvailable()) {
            return [
                'generated_at' => now()->toIso8601String(),
                'period_days' => $days,
                'telemetry_available' => false,
                'status' => 'UNAVAILABLE',
                'message' => 'Phase A telemetry tables are not available.',
            ];
        }

        $acceptance = $this->acceptanceMetrics($since);
        $outcomes = $this->outcomeMetrics($since);
        $matching = $this->matchingMetrics($since);
        $candidates = $this->candidateMetrics($since);
        $milestones = $this->milestoneProgress($milestoneMinAccepted, $milestoneMinResolved, $candidates['ready_for_promotion']);

        return [
            'generated_at' => now()->toIso8601String(),
            'period_days' => $days,
            'period_since' => $since->toIso8601String(),
            'telemetry_available' => true,
            'acceptance' => $acceptance,
            'outcomes' => $outcomes,
            'matching' => $matching,
            'candidates' => $candidates,
            'milestones' => $milestones,
            'phase_a_status' => $milestones['ready_for_next_milestone'] ? 'READY' : 'WAITING',
            'message' => $milestones['ready_for_next_milestone']
                ? 'Phase A milestones met. Next learning milestone may begin.'
                : 'Phase A complete — waiting for more accepted operator examples and resolved conversations before the next milestone.',
            'status' => 'PASS',
        ];
    }

    /**
     * Compact payload for admin diagnostics widget (read-only).
     *
     * @return array<string, mixed>|null
     */
    public function widgetSnapshot(): ?array
    {
        if (! $this->isEnabled() || ! $this->isTelemetryAvailable()) {
            return null;
        }

        $report = $this->buildReport();

        return [
            'generated_at' => $report['generated_at'],
            'period_days' => $report['period_days'],
            'accepted_total' => $report['acceptance']['all_time']['accepted_total'] ?? 0,
            'resolved_total' => $report['outcomes']['all_time']['resolved'] ?? 0,
            'ready_for_promotion' => $report['candidates']['ready_for_promotion'] ?? 0,
            'quarantined' => $report['candidates']['quarantined'] ?? 0,
            'unknown_rate_pct' => $report['matching']['unknown_rate_pct'] ?? null,
            'phase_a_status' => $report['phase_a_status'] ?? 'WAITING',
            'milestones' => $report['milestones'] ?? [],
            'message' => $report['message'] ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function acceptanceMetrics(Carbon $since): array
    {
        $periodQuery = SupportAiSuggestionUsage::query()->where('created_at', '>=', $since);
        $period = $this->usageDecisionCounts($periodQuery);

        $allTime = $this->usageDecisionCounts(SupportAiSuggestionUsage::query());

        return [
            'period' => $period,
            'all_time' => $allTime,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function outcomeMetrics(Carbon $since): array
    {
        $periodQuery = SupportAiConversationOutcome::query()->where('updated_at', '>=', $since);
        $period = $this->outcomeCounts($periodQuery);

        $allTime = $this->outcomeCounts(SupportAiConversationOutcome::query());

        return [
            'period' => $period,
            'all_time' => $allTime,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function matchingMetrics(Carbon $since): array
    {
        $query = SupportAiSuggestionUsage::query()->where('created_at', '>=', $since);
        $total = (clone $query)->count();
        $unknown = (clone $query)->where('decision', SupportAiSuggestionUsage::DECISION_UNKNOWN)->count();
        $orphan = (clone $query)->where('matched_by', SupportAiSuggestionUsage::MATCHED_BY_FALLBACK_UNKNOWN)->count();

        $matchedBy = SupportAiSuggestionUsage::query()
            ->selectRaw('matched_by, COUNT(*) as total')
            ->where('created_at', '>=', $since)
            ->groupBy('matched_by')
            ->pluck('total', 'matched_by')
            ->all();

        return [
            'total_usage_records' => $total,
            'unknown' => $unknown,
            'unknown_rate_pct' => $total > 0 ? round(($unknown / $total) * 100, 1) : null,
            'orphan_or_unknown' => $unknown + $orphan,
            'matched_by' => $matchedBy,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function candidateMetrics(Carbon $since): array
    {
        if (! Schema::hasTable('support_ai_learning_candidates')) {
            return [
                'total' => 0,
                'quarantined' => 0,
                'ready_for_promotion' => 0,
                'staged' => 0,
                'approved' => 0,
                'rejected' => 0,
            ];
        }

        $promotion = app(SupportAiLearningPromotionService::class);
        $safetyFilter = app(SupportAiCandidateSafetyFilterService::class);

        $ready = 0;
        $quarantined = 0;

        foreach (SupportAiLearningCandidate::query()->get() as $candidate) {
            if ($safetyFilter->isQuarantined($candidate)) {
                $quarantined++;
            }
            if ($promotion->isPromotable($candidate)
                && in_array($candidate->status, [
                    SupportAiLearningCandidate::STATUS_STAGED,
                    SupportAiLearningCandidate::STATUS_APPROVED,
                    SupportAiLearningCandidate::STATUS_PENDING,
                ], true)) {
                $ready++;
            }
        }

        return [
            'total' => SupportAiLearningCandidate::query()->count(),
            'created_in_period' => SupportAiLearningCandidate::query()->where('created_at', '>=', $since)->count(),
            'quarantined' => $quarantined,
            'ready_for_promotion' => $ready,
            'staged' => SupportAiLearningCandidate::query()->where('status', SupportAiLearningCandidate::STATUS_STAGED)->count(),
            'approved' => SupportAiLearningCandidate::query()->where('status', SupportAiLearningCandidate::STATUS_APPROVED)->count(),
            'rejected' => SupportAiLearningCandidate::query()->where('status', SupportAiLearningCandidate::STATUS_REJECTED)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function milestoneProgress(int $minAccepted, int $minResolved, int $readyForPromotion): array
    {
        $acceptedTotal = (int) SupportAiSuggestionUsage::query()
            ->whereIn('decision', [
                SupportAiSuggestionUsage::DECISION_ACCEPTED_EXACT,
                SupportAiSuggestionUsage::DECISION_ACCEPTED_MODIFIED,
            ])
            ->count();

        $resolvedTotal = (int) SupportAiConversationOutcome::query()
            ->where('outcome', SupportAiConversationOutcome::OUTCOME_RESOLVED)
            ->count();

        $gates = [
            'accepted_samples' => [
                'current' => $acceptedTotal,
                'required' => $minAccepted,
                'met' => $acceptedTotal >= $minAccepted,
            ],
            'resolved_conversations' => [
                'current' => $resolvedTotal,
                'required' => $minResolved,
                'met' => $resolvedTotal >= $minResolved,
            ],
            'ready_for_promotion' => [
                'current' => $readyForPromotion,
                'required' => 1,
                'met' => $readyForPromotion > 0,
            ],
        ];

        $readyForNext = $gates['accepted_samples']['met']
            && $gates['resolved_conversations']['met']
            && $gates['ready_for_promotion']['met'];

        return [
            'gates' => $gates,
            'ready_for_next_milestone' => $readyForNext,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<SupportAiSuggestionUsage>  $query
     * @return array<string, int|float|null>
     */
    private function usageDecisionCounts($query): array
    {
        $total = (clone $query)->count();
        $acceptedExact = (clone $query)->where('decision', SupportAiSuggestionUsage::DECISION_ACCEPTED_EXACT)->count();
        $acceptedModified = (clone $query)->where('decision', SupportAiSuggestionUsage::DECISION_ACCEPTED_MODIFIED)->count();
        $ignored = (clone $query)->where('decision', SupportAiSuggestionUsage::DECISION_IGNORED)->count();
        $unknown = (clone $query)->where('decision', SupportAiSuggestionUsage::DECISION_UNKNOWN)->count();
        $acceptedTotal = $acceptedExact + $acceptedModified;

        return [
            'total' => $total,
            'accepted_exact' => $acceptedExact,
            'accepted_modified' => $acceptedModified,
            'accepted_total' => $acceptedTotal,
            'ignored' => $ignored,
            'unknown' => $unknown,
            'acceptance_rate_pct' => $total > 0 ? round(($acceptedTotal / $total) * 100, 1) : null,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<SupportAiConversationOutcome>  $query
     * @return array<string, int|float|null>
     */
    private function outcomeCounts($query): array
    {
        $total = (clone $query)->count();
        $resolved = (clone $query)->where('outcome', SupportAiConversationOutcome::OUTCOME_RESOLVED)->count();

        return [
            'total' => $total,
            'resolved' => $resolved,
            'pending' => (clone $query)->where('outcome', SupportAiConversationOutcome::OUTCOME_PENDING)->count(),
            'failed' => (clone $query)->where('outcome', SupportAiConversationOutcome::OUTCOME_FAILED)->count(),
            'reopened' => (clone $query)->where('outcome', SupportAiConversationOutcome::OUTCOME_REOPENED)->count(),
            'resolution_rate_pct' => $total > 0 ? round(($resolved / $total) * 100, 1) : null,
        ];
    }
}
