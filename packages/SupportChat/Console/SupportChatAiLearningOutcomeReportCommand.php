<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use App\Models\SupportAiConversationOutcome;
use App\Models\SupportAiSuggestionUsage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SupportChatAiLearningOutcomeReportCommand extends Command
{
    protected $signature = 'support-chat:ai-learning-outcome-report
                            {--days=30 : Lookback window in days}
                            {--json : Output machine-readable JSON}';

    protected $description = 'Report AI support conversation outcomes and acceptance correlation.';

    public function handle(): int
    {
        if (! Schema::hasTable('support_ai_conversation_outcomes')) {
            $this->error('support_ai_conversation_outcomes table is not available. Run migrations first.');

            return self::FAILURE;
        }

        $days = max(1, min(365, (int) $this->option('days')));
        $since = Carbon::now()->subDays($days);

        $outcomeQuery = SupportAiConversationOutcome::query()->where('updated_at', '>=', $since);
        $totalConversations = (clone $outcomeQuery)->count();

        $resolved = (clone $outcomeQuery)->where('outcome', SupportAiConversationOutcome::OUTCOME_RESOLVED)->count();
        $pending = (clone $outcomeQuery)->where('outcome', SupportAiConversationOutcome::OUTCOME_PENDING)->count();
        $escalated = (clone $outcomeQuery)->where('outcome', SupportAiConversationOutcome::OUTCOME_ESCALATED)->count();
        $failed = (clone $outcomeQuery)->where('outcome', SupportAiConversationOutcome::OUTCOME_FAILED)->count();
        $reopened = (clone $outcomeQuery)->where('outcome', SupportAiConversationOutcome::OUTCOME_REOPENED)->count();
        $unknown = (clone $outcomeQuery)->where('outcome', SupportAiConversationOutcome::OUTCOME_UNKNOWN)->count();

        $resolutionRate = $totalConversations > 0
            ? round(($resolved / $totalConversations) * 100, 1)
            : null;

        $avgTtr = (clone $outcomeQuery)
            ->where('outcome', SupportAiConversationOutcome::OUTCOME_RESOLVED)
            ->whereNotNull('time_to_resolution_seconds')
            ->avg('time_to_resolution_seconds');

        $correlation = $this->buildAcceptanceCorrelation($since);

        $report = [
            'period_days' => $days,
            'period_since' => $since->toIso8601String(),
            'total_conversations' => $totalConversations,
            'resolved' => $resolved,
            'pending' => $pending,
            'escalated' => $escalated,
            'failed' => $failed,
            'reopened' => $reopened,
            'unknown' => $unknown,
            'resolution_rate_pct' => $resolutionRate,
            'average_time_to_resolution_seconds' => $avgTtr !== null ? (int) round((float) $avgTtr) : null,
            'accepted_suggestions_in_resolved_conversations' => $correlation['accepted_in_resolved'],
            'ignored_suggestions_in_unresolved_conversations' => $correlation['ignored_in_unresolved'],
            'correlation' => [
                'accepted_and_resolved' => $correlation['accepted_and_resolved'],
                'accepted_and_unresolved' => $correlation['accepted_and_unresolved'],
                'ignored_and_resolved' => $correlation['ignored_and_resolved'],
                'ignored_and_unresolved' => $correlation['ignored_and_unresolved'],
            ],
            'status' => 'PASS',
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line('AI Support Outcome Report');
        $this->line('');
        $this->line('Period: '.$days.' days (since '.$since->toDateTimeString().')');
        $this->line('Total conversations: '.$totalConversations);
        $this->line('Resolved: '.$resolved);
        $this->line('Pending: '.$pending);
        $this->line('Escalated: '.$escalated);
        $this->line('Failed: '.$failed);
        $this->line('Reopened: '.$reopened);
        $this->line('Unknown: '.$unknown);
        $this->line('');
        $this->line('Resolution rate: '.($resolutionRate !== null ? $resolutionRate.'%' : 'n/a'));
        $this->line('Average time to resolution: '.($avgTtr !== null ? (int) round((float) $avgTtr).'s' : 'n/a'));
        $this->line('');
        $this->line('Accepted suggestions in resolved conversations: '.$correlation['accepted_in_resolved']);
        $this->line('Ignored suggestions in unresolved conversations: '.$correlation['ignored_in_unresolved']);
        $this->line('');
        $this->line('Correlation:');
        $this->line('- accepted_and_resolved: '.$correlation['accepted_and_resolved']);
        $this->line('- accepted_and_unresolved: '.$correlation['accepted_and_unresolved']);
        $this->line('- ignored_and_resolved: '.$correlation['ignored_and_resolved']);
        $this->line('- ignored_and_unresolved: '.$correlation['ignored_and_unresolved']);
        $this->line('');
        $this->line('Result: PASS');

        return self::SUCCESS;
    }

    /**
     * @return array{
     *     accepted_and_resolved: int,
     *     accepted_and_unresolved: int,
     *     ignored_and_resolved: int,
     *     ignored_and_unresolved: int,
     *     accepted_in_resolved: int,
     *     ignored_in_unresolved: int
     * }
     */
    private function buildAcceptanceCorrelation(Carbon $since): array
    {
        if (! Schema::hasTable('support_ai_suggestion_usages')) {
            return [
                'accepted_and_resolved' => 0,
                'accepted_and_unresolved' => 0,
                'ignored_and_resolved' => 0,
                'ignored_and_unresolved' => 0,
                'accepted_in_resolved' => 0,
                'ignored_in_unresolved' => 0,
            ];
        }

        $acceptedDecisions = [
            SupportAiSuggestionUsage::DECISION_ACCEPTED_EXACT,
            SupportAiSuggestionUsage::DECISION_ACCEPTED_MODIFIED,
        ];

        $resolvedOutcome = SupportAiConversationOutcome::OUTCOME_RESOLVED;
        $unresolvedOutcomes = [
            SupportAiConversationOutcome::OUTCOME_PENDING,
            SupportAiConversationOutcome::OUTCOME_ESCALATED,
            SupportAiConversationOutcome::OUTCOME_REOPENED,
            SupportAiConversationOutcome::OUTCOME_UNKNOWN,
            SupportAiConversationOutcome::OUTCOME_FAILED,
        ];

        $base = DB::table('support_ai_suggestion_usages as u')
            ->join('support_ai_conversation_outcomes as o', 'o.conversation_id', '=', 'u.conversation_id')
            ->where('u.created_at', '>=', $since);

        $acceptedAndResolved = (clone $base)
            ->whereIn('u.decision', $acceptedDecisions)
            ->where('o.outcome', $resolvedOutcome)
            ->count();

        $acceptedAndUnresolved = (clone $base)
            ->whereIn('u.decision', $acceptedDecisions)
            ->whereIn('o.outcome', $unresolvedOutcomes)
            ->count();

        $ignoredAndResolved = (clone $base)
            ->where('u.decision', SupportAiSuggestionUsage::DECISION_IGNORED)
            ->where('o.outcome', $resolvedOutcome)
            ->count();

        $ignoredAndUnresolved = (clone $base)
            ->where('u.decision', SupportAiSuggestionUsage::DECISION_IGNORED)
            ->whereIn('o.outcome', $unresolvedOutcomes)
            ->count();

        return [
            'accepted_and_resolved' => (int) $acceptedAndResolved,
            'accepted_and_unresolved' => (int) $acceptedAndUnresolved,
            'ignored_and_resolved' => (int) $ignoredAndResolved,
            'ignored_and_unresolved' => (int) $ignoredAndUnresolved,
            'accepted_in_resolved' => (int) $acceptedAndResolved,
            'ignored_in_unresolved' => (int) $ignoredAndUnresolved,
        ];
    }
}
