<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use iEXPackages\SupportChat\Services\SupportAiCandidateSafetyFilterService;
use Illuminate\Console\Command;

final class SupportChatAiLearningFilterCandidatesCommand extends Command
{
    protected $signature = 'support-chat:ai-learning-filter-candidates
                            {--days=30 : Lookback window in days}
                            {--dry-run : Report only, do not write filter results}
                            {--json : Output machine-readable JSON}';

    protected $description = 'Apply deterministic safety filter to learning candidates (eligible vs quarantined).';

    public function handle(SupportAiCandidateSafetyFilterService $filter): int
    {
        if (! $filter->isEnabled()) {
            $this->warn('Candidate filtering disabled (SUPPORT_AI_CANDIDATE_FILTERING_ENABLED=0).');

            return self::SUCCESS;
        }

        $days = max(1, min(365, (int) $this->option('days')));
        $dryRun = (bool) $this->option('dry-run');

        $result = $filter->filterCandidatesInWindow($days, $dryRun);
        $report = array_merge($result, [
            'period_days' => $days,
            'status' => 'PASS',
        ]);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line('AI Learning Candidate Safety Filter');
        $this->line('');
        $this->line('Period: '.$days.' days');
        $this->line('Dry run: '.($dryRun ? 'yes' : 'no'));
        $this->line('Total candidates scanned: '.$result['total']);
        $this->line('Eligible: '.$result['eligible']);
        $this->line('Quarantined: '.$result['quarantined']);
        $this->line('');
        $this->line('High severity: '.($result['severity_counts']['high'] ?? 0));
        $this->line('Medium severity: '.($result['severity_counts']['medium'] ?? 0));
        $this->line('Low severity: '.($result['severity_counts']['low'] ?? 0));
        $this->line('');
        $this->line('Linked to ignored usage: '.$result['linked_ignored']);
        $this->line('Linked to unknown usage: '.$result['linked_unknown']);
        $this->line('Linked to failed/reopened outcomes: '.$result['linked_failed_reopened']);
        $this->line('Blocked by unsafe wording: '.$result['blocked_unsafe_wording']);
        $this->line('');
        $this->line('Quarantine reasons:');
        if ($result['reason_counts'] === []) {
            $this->line('- (none)');
        } else {
            foreach ($result['reason_counts'] as $reason => $count) {
                $this->line('- '.$reason.': '.$count);
            }
        }
        $this->line('');
        $this->line('Result: PASS');

        return self::SUCCESS;
    }
}
