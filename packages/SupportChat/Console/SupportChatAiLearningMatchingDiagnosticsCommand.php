<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use App\Models\SupportAiLearningEvent;
use App\Models\SupportAiSuggestionUsage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SupportChatAiLearningMatchingDiagnosticsCommand extends Command
{
    protected $signature = 'support-chat:ai-learning-matching-diagnostics
                            {--days=30 : Lookback window in days}
                            {--json : Output machine-readable JSON}';

    protected $description = 'Report AI suggestion matching quality and orphan/unknown rates.';

    public function handle(): int
    {
        if (! filter_var(config('support_chat.ai.matching_diagnostics.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->warn('Matching diagnostics disabled (SUPPORT_AI_MATCHING_DIAGNOSTICS_ENABLED=0).');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('support_ai_suggestion_usages')) {
            $this->error('support_ai_suggestion_usages table is not available.');

            return self::FAILURE;
        }

        $days = max(1, min(365, (int) $this->option('days')));
        $since = Carbon::now()->subDays($days);

        $usageQuery = SupportAiSuggestionUsage::query()->where('created_at', '>=', $since);
        $total = (clone $usageQuery)->count();

        $matchedByCounts = $this->countByMatchedBy($since);
        $decisionCounts = $this->countByDecision($since);

        $unknown = (int) ($decisionCounts[SupportAiSuggestionUsage::DECISION_UNKNOWN] ?? 0);
        $ignored = (int) ($decisionCounts[SupportAiSuggestionUsage::DECISION_IGNORED] ?? 0);
        $acceptedExact = (int) ($decisionCounts[SupportAiSuggestionUsage::DECISION_ACCEPTED_EXACT] ?? 0);
        $acceptedModified = (int) ($decisionCounts[SupportAiSuggestionUsage::DECISION_ACCEPTED_MODIFIED] ?? 0);

        $unknownRate = $total > 0 ? round(($unknown / $total) * 100, 1) : null;
        $ignoredRate = $total > 0 ? round(($ignored / $total) * 100, 1) : null;

        $orphanMatchedBy = (int) (($matchedByCounts[SupportAiSuggestionUsage::MATCHED_BY_FALLBACK_UNKNOWN] ?? 0)
            + ($matchedByCounts['unknown'] ?? 0));

        $duplicateFingerprints = $this->duplicateFingerprintCount($since);
        $topUnknownConversations = $this->topUnknownConversations($since);
        $recentUnknownSamples = $this->recentUnknownSamples($since);

        $report = [
            'period_days' => $days,
            'period_since' => $since->toIso8601String(),
            'total_usage_records' => $total,
            'matched_by' => $matchedByCounts,
            'matched_by_lineage' => (int) ($matchedByCounts[SupportAiSuggestionUsage::MATCHED_BY_LINEAGE] ?? 0),
            'matched_by_telegram_ai_message' => (int) ($matchedByCounts[SupportAiSuggestionUsage::MATCHED_BY_TELEGRAM_AI_MESSAGE] ?? 0),
            'matched_by_visitor_anchor' => (int) (($matchedByCounts[SupportAiSuggestionUsage::MATCHED_BY_VISITOR_ANCHOR] ?? 0)
                + ($matchedByCounts[SupportAiSuggestionUsage::MATCHED_BY_SAME_VISITOR_MESSAGE] ?? 0)),
            'matched_by_event_fingerprint' => (int) ($matchedByCounts[SupportAiSuggestionUsage::MATCHED_BY_EVENT_FINGERPRINT] ?? 0),
            'matched_by_same_conversation_recent' => (int) ($matchedByCounts[SupportAiSuggestionUsage::MATCHED_BY_SAME_CONVERSATION_RECENT] ?? 0),
            'matched_by_text_similarity' => (int) ($matchedByCounts[SupportAiSuggestionUsage::MATCHED_BY_TEXT_SIMILARITY] ?? 0),
            'unknown_or_orphan' => $orphanMatchedBy + $unknown,
            'unknown_rate_pct' => $unknownRate,
            'ignored_rate_pct' => $ignoredRate,
            'accepted_exact' => $acceptedExact,
            'accepted_modified' => $acceptedModified,
            'duplicate_learning_event_fingerprints' => $duplicateFingerprints,
            'top_conversations_with_unknown_matches' => $topUnknownConversations,
            'recent_unknown_samples' => $recentUnknownSamples,
            'status' => 'PASS',
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line('AI Learning Matching Diagnostics');
        $this->line('');
        $this->line('Period: '.$days.' days (since '.$since->toDateTimeString().')');
        $this->line('Total usage records: '.$total);
        $this->line('');
        $this->line('Matched by lineage: '.$report['matched_by_lineage']);
        $this->line('Matched by Telegram AI message: '.$report['matched_by_telegram_ai_message']);
        $this->line('Matched by visitor anchor: '.$report['matched_by_visitor_anchor']);
        $this->line('Matched by event fingerprint: '.$report['matched_by_event_fingerprint']);
        $this->line('Matched by recent conversation: '.$report['matched_by_same_conversation_recent']);
        $this->line('Matched by text similarity: '.$report['matched_by_text_similarity']);
        $this->line('Unknown/orphan: '.$report['unknown_or_orphan']);
        $this->line('');
        $this->line('Unknown rate: '.($unknownRate !== null ? $unknownRate.'%' : 'n/a'));
        $this->line('Ignored rate: '.($ignoredRate !== null ? $ignoredRate.'%' : 'n/a'));
        $this->line('Accepted exact: '.$acceptedExact);
        $this->line('Accepted modified: '.$acceptedModified);
        $this->line('');
        $this->line('Duplicate learning event fingerprints: '.$duplicateFingerprints);
        $this->line('');
        $this->line('Top conversations with unknown matches:');
        if ($topUnknownConversations === []) {
            $this->line('- (none)');
        } else {
            foreach ($topUnknownConversations as $row) {
                $this->line('- conv='.$row['conversation_id'].' unknown='.$row['count']);
            }
        }
        $this->line('');
        $this->line('Recent unknown samples:');
        if ($recentUnknownSamples === []) {
            $this->line('- (none)');
        } else {
            foreach ($recentUnknownSamples as $row) {
                $this->line('- #'.$row['id'].' conv='.$row['conversation_id'].' matched_by='.$row['matched_by']);
            }
        }
        $this->line('');
        $this->line('Result: PASS');

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function countByMatchedBy(Carbon $since): array
    {
        $rows = SupportAiSuggestionUsage::query()
            ->selectRaw('matched_by, COUNT(*) as total')
            ->where('created_at', '>=', $since)
            ->groupBy('matched_by')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $key = (string) ($row->matched_by ?? 'unknown');
            $out[$key] = (int) $row->total;
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    private function countByDecision(Carbon $since): array
    {
        $rows = SupportAiSuggestionUsage::query()
            ->selectRaw('decision, COUNT(*) as total')
            ->where('created_at', '>=', $since)
            ->groupBy('decision')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->decision] = (int) $row->total;
        }

        return $out;
    }

    private function duplicateFingerprintCount(Carbon $since): int
    {
        if (! Schema::hasTable('support_ai_learning_events')) {
            return 0;
        }

        $rows = DB::table('support_ai_learning_events')
            ->selectRaw('JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.event_fingerprint")) as fp, COUNT(*) as total')
            ->where('created_at', '>=', $since)
            ->whereNotNull('metadata')
            ->groupBy('fp')
            ->having('total', '>', 1)
            ->get();

        return $rows->count();
    }

    /**
     * @return list<array{conversation_id: int, count: int}>
     */
    private function topUnknownConversations(Carbon $since): array
    {
        $rows = SupportAiSuggestionUsage::query()
            ->selectRaw('conversation_id, COUNT(*) as total')
            ->where('created_at', '>=', $since)
            ->where(function ($query): void {
                $query->where('decision', SupportAiSuggestionUsage::DECISION_UNKNOWN)
                    ->orWhere('matched_by', SupportAiSuggestionUsage::MATCHED_BY_FALLBACK_UNKNOWN);
            })
            ->whereNotNull('conversation_id')
            ->groupBy('conversation_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return $rows->map(static fn ($row): array => [
            'conversation_id' => (int) $row->conversation_id,
            'count' => (int) $row->total,
        ])->all();
    }

    /**
     * @return list<array{id: int, conversation_id: int|null, matched_by: string|null, created_at: string|null}>
     */
    private function recentUnknownSamples(Carbon $since): array
    {
        return SupportAiSuggestionUsage::query()
            ->where('created_at', '>=', $since)
            ->where(function ($query): void {
                $query->where('decision', SupportAiSuggestionUsage::DECISION_UNKNOWN)
                    ->orWhere('matched_by', SupportAiSuggestionUsage::MATCHED_BY_FALLBACK_UNKNOWN);
            })
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'conversation_id', 'matched_by', 'created_at'])
            ->map(static fn (SupportAiSuggestionUsage $row): array => [
                'id' => (int) $row->id,
                'conversation_id' => $row->conversation_id !== null ? (int) $row->conversation_id : null,
                'matched_by' => $row->matched_by,
                'created_at' => $row->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
