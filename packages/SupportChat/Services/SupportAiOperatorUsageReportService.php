<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Services;

use App\Models\SupportAiOperatorUsageMetric;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * LC-H: aggregate operator AI usage metrics for read-only CLI reports.
 */
final class SupportAiOperatorUsageReportService
{
    public function __construct(
        private readonly SupportAiOperatorUsageService $usage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildReport(Carbon $since): array
    {
        if (! Schema::hasTable('support_ai_operator_usage_metrics')) {
            return [
                'status' => 'FAIL',
                'error' => 'support_ai_operator_usage_metrics table is not available. Run migrations first.',
            ];
        }

        $query = SupportAiOperatorUsageMetric::query()->where('draft_generated_at', '>=', $since);
        $totalDrafts = (clone $query)->count();

        $acceptedExact = (clone $query)->where('operator_decision', SupportAiOperatorUsageMetric::DECISION_ACCEPTED_EXACT)->count();
        $acceptedModified = (clone $query)->where('operator_decision', SupportAiOperatorUsageMetric::DECISION_ACCEPTED_MODIFIED)->count();
        $ignored = (clone $query)->where('operator_decision', SupportAiOperatorUsageMetric::DECISION_IGNORED)->count();
        $unknown = (clone $query)->where('operator_decision', SupportAiOperatorUsageMetric::DECISION_UNKNOWN)->count();
        $pending = (clone $query)->where('operator_decision', SupportAiOperatorUsageMetric::DECISION_PENDING)->count();

        $withOperatorReply = $acceptedExact + $acceptedModified + $ignored + $unknown;
        $accepted = $acceptedExact + $acceptedModified;

        $acceptanceRate = $withOperatorReply > 0
            ? round(($accepted / $withOperatorReply) * 100, 1)
            : null;

        $exactRate = $withOperatorReply > 0
            ? round(($acceptedExact / $withOperatorReply) * 100, 1)
            : null;

        $avgResponseTime = (clone $query)
            ->whereNotNull('response_time_seconds')
            ->avg('response_time_seconds');

        $topIntents = (clone $query)
            ->selectRaw('intent, COUNT(*) as total')
            ->whereNotNull('intent')
            ->where('intent', '!=', '')
            ->groupBy('intent')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(static fn ($row): array => [
                'intent' => (string) $row->intent,
                'count' => (int) $row->total,
            ])
            ->all();

        $orderLookupUsed = (clone $query)->where('order_lookup_used', true)->count();
        $directionLookupUsed = (clone $query)->where('direction_lookup_used', true)->count();

        $examples = (clone $query)
            ->whereNotNull('operator_decision')
            ->where('operator_decision', '!=', SupportAiOperatorUsageMetric::DECISION_PENDING)
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (SupportAiOperatorUsageMetric $row): array => [
                'conversation_id' => $row->conversation_id,
                'intent' => $row->intent,
                'operator_decision' => $row->operator_decision,
                'response_time_seconds' => $row->response_time_seconds,
                'order_lookup_used' => (bool) $row->order_lookup_used,
                'direction_lookup_used' => (bool) $row->direction_lookup_used,
                'similarity_score' => $row->similarity_score,
                'suggestion_preview' => $row->suggestion_preview,
                'operator_reply_preview' => $row->operator_reply_preview,
            ])
            ->all();

        return [
            'period_since' => $since->toIso8601String(),
            'period_until' => now()->toIso8601String(),
            'total_drafts' => $totalDrafts,
            'accepted_exact' => $acceptedExact,
            'accepted_modified' => $acceptedModified,
            'edited' => $acceptedModified,
            'ignored' => $ignored,
            'unknown' => $unknown,
            'pending' => $pending,
            'with_operator_reply' => $withOperatorReply,
            'acceptance_rate_pct' => $acceptanceRate,
            'exact_acceptance_rate_pct' => $exactRate,
            'average_response_time_seconds' => $avgResponseTime !== null ? round((float) $avgResponseTime, 1) : null,
            'order_lookup_used_count' => $orderLookupUsed,
            'direction_lookup_used_count' => $directionLookupUsed,
            'top_intents' => $topIntents,
            'examples' => $examples,
            'status' => 'PASS',
        ];
    }

    public function parseSinceOption(string $sinceRaw): Carbon
    {
        $sinceRaw = trim($sinceRaw);
        if ($sinceRaw === '') {
            return now()->subDay();
        }

        try {
            $timestamp = strtotime($sinceRaw);
            if ($timestamp !== false) {
                return Carbon::createFromTimestamp($timestamp);
            }
        } catch (Throwable) {
            // fall through
        }

        return Carbon::parse($sinceRaw);
    }
}
