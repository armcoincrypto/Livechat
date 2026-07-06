<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use App\Models\SupportAiSuggestionUsage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

final class SupportChatAiLearningAcceptanceReportCommand extends Command
{
    protected $signature = 'support-chat:ai-learning-acceptance-report
                            {--days=30 : Lookback window in days}
                            {--json : Output machine-readable JSON}';

    protected $description = 'Report AI suggestion acceptance telemetry (exact/modified/ignored/unknown).';

    public function handle(): int
    {
        if (! Schema::hasTable('support_ai_suggestion_usages')) {
            $this->error('support_ai_suggestion_usages table is not available. Run migrations first.');

            return self::FAILURE;
        }

        $days = max(1, min(365, (int) $this->option('days')));
        $since = Carbon::now()->subDays($days);

        $query = SupportAiSuggestionUsage::query()->where('created_at', '>=', $since);
        $total = (clone $query)->count();

        $acceptedExact = (clone $query)->where('decision', SupportAiSuggestionUsage::DECISION_ACCEPTED_EXACT)->count();
        $acceptedModified = (clone $query)->where('decision', SupportAiSuggestionUsage::DECISION_ACCEPTED_MODIFIED)->count();
        $ignored = (clone $query)->where('decision', SupportAiSuggestionUsage::DECISION_IGNORED)->count();
        $unknown = (clone $query)->where('decision', SupportAiSuggestionUsage::DECISION_UNKNOWN)->count();

        $classified = $acceptedExact + $acceptedModified + $ignored;
        $accepted = $acceptedExact + $acceptedModified;

        $acceptanceRateAll = $total > 0 ? round(($accepted / $total) * 100, 1) : null;
        $acceptanceRateClassified = $classified > 0 ? round(($accepted / $classified) * 100, 1) : null;
        $exactRate = $total > 0 ? round(($acceptedExact / $total) * 100, 1) : null;
        $modifiedRate = $total > 0 ? round(($acceptedModified / $total) * 100, 1) : null;
        $ignoredRate = $total > 0 ? round(($ignored / $total) * 100, 1) : null;
        $unknownRate = $total > 0 ? round(($unknown / $total) * 100, 1) : null;

        $avgSimilarity = (clone $query)
            ->whereNotNull('similarity_score')
            ->avg('similarity_score');

        $matchedByRows = (clone $query)
            ->selectRaw('matched_by, COUNT(*) as total')
            ->whereNotNull('matched_by')
            ->groupBy('matched_by')
            ->orderByDesc('total')
            ->get()
            ->map(static fn ($row): array => [
                'matched_by' => (string) $row->matched_by,
                'count' => (int) $row->total,
            ])
            ->all();

        $recentSample = (clone $query)
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'conversation_id', 'decision', 'similarity_score', 'matched_by', 'created_at'])
            ->map(static fn (SupportAiSuggestionUsage $row): array => [
                'id' => (int) $row->id,
                'conversation_id' => $row->conversation_id,
                'decision' => $row->decision,
                'similarity_score' => $row->similarity_score,
                'matched_by' => $row->matched_by,
                'created_at' => $row->created_at?->toIso8601String(),
            ])
            ->all();

        $report = [
            'period_days' => $days,
            'period_since' => $since->toIso8601String(),
            'total_usage_records' => $total,
            'accepted_exact' => $acceptedExact,
            'accepted_modified' => $acceptedModified,
            'ignored' => $ignored,
            'unknown' => $unknown,
            'acceptance_rate_all_pct' => $acceptanceRateAll,
            'acceptance_rate_classified_pct' => $acceptanceRateClassified,
            'exact_acceptance_rate_pct' => $exactRate,
            'modified_acceptance_rate_pct' => $modifiedRate,
            'ignored_rate_pct' => $ignoredRate,
            'unknown_rate_pct' => $unknownRate,
            'average_similarity' => $avgSimilarity !== null ? round((float) $avgSimilarity, 4) : null,
            'top_matched_by' => $matchedByRows,
            'recent_records_sample' => $recentSample,
            'status' => 'PASS',
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line('AI Suggestion Acceptance Report');
        $this->line('');
        $this->line('Period: '.$days.' days (since '.$since->toDateTimeString().')');
        $this->line('Total usage records: '.$total);
        $this->line('Accepted exact: '.$acceptedExact);
        $this->line('Accepted modified: '.$acceptedModified);
        $this->line('Ignored: '.$ignored);
        $this->line('Unknown: '.$unknown);
        $this->line('');
        $this->line('Acceptance rate (all): '.($acceptanceRateAll !== null ? $acceptanceRateAll.'%' : 'n/a'));
        $this->line('Acceptance rate (classified): '.($acceptanceRateClassified !== null ? $acceptanceRateClassified.'%' : 'n/a'));
        $this->line('Exact acceptance rate: '.($exactRate !== null ? $exactRate.'%' : 'n/a'));
        $this->line('Modified acceptance rate: '.($modifiedRate !== null ? $modifiedRate.'%' : 'n/a'));
        $this->line('Ignored rate: '.($ignoredRate !== null ? $ignoredRate.'%' : 'n/a'));
        $this->line('');
        $this->line('Average similarity: '.($avgSimilarity !== null ? round((float) $avgSimilarity, 4) : 'n/a'));
        $this->line('');
        $this->line('Top matched_by values:');
        if ($matchedByRows === []) {
            $this->line('- (none)');
        } else {
            foreach ($matchedByRows as $row) {
                $this->line('- '.$row['matched_by'].': '.$row['count']);
            }
        }
        $this->line('');
        $this->line('Recent records sample:');
        if ($recentSample === []) {
            $this->line('- (none)');
        } else {
            foreach ($recentSample as $row) {
                $this->line('- #'.$row['id'].' conv='.$row['conversation_id'].' '.$row['decision']
                    .' sim='.($row['similarity_score'] ?? 'n/a').' via='.$row['matched_by']);
            }
        }
        $this->line('');
        $this->line('Result: PASS');

        return self::SUCCESS;
    }
}
