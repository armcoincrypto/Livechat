<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use iEXPackages\SupportChat\Services\SupportAiOperatorUsageReportService;
use iEXPackages\SupportChat\Services\SupportAiOperatorUsageService;
use Illuminate\Console\Command;

final class SupportAiUsageReportCommand extends Command
{
    protected $signature = 'support-ai:usage-report
                            {--since=24 hours ago : Start of reporting window (strtotime-compatible)}
                            {--backfill : Backfill metrics from learning events and suggestion usages}
                            {--json : Output machine-readable JSON}';

    protected $description = 'LC-H: read-only operator AI draft usage report (redacted previews only).';

    public function handle(
        SupportAiOperatorUsageReportService $reportService,
        SupportAiOperatorUsageService $usageService,
    ): int {
        $since = $reportService->parseSinceOption((string) $this->option('since'));

        if ((bool) $this->option('backfill')) {
            $backfilled = $usageService->backfillFromHistoricalTelemetry($since);
            $this->line('Backfilled '.$backfilled.' metric row(s) from historical telemetry.');
        }

        $report = $reportService->buildReport($since);

        if (($report['status'] ?? '') === 'FAIL') {
            $this->error((string) ($report['error'] ?? 'Report failed.'));

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line('Support AI Operator Usage Report (LC-H)');
        $this->line('');
        $this->line('Period: since '.$since->toDateTimeString().' until '.now()->toDateTimeString());
        $this->line('Total drafts: '.($report['total_drafts'] ?? 0));
        $this->line('Accepted exact: '.($report['accepted_exact'] ?? 0));
        $this->line('Edited (modified): '.($report['edited'] ?? 0));
        $this->line('Ignored: '.($report['ignored'] ?? 0));
        $this->line('Unknown: '.($report['unknown'] ?? 0));
        $this->line('Pending (no operator reply yet): '.($report['pending'] ?? 0));
        $this->line('');
        $this->line('Acceptance rate (classified replies): '
            .($report['acceptance_rate_pct'] !== null ? $report['acceptance_rate_pct'].'%' : 'n/a'));
        $this->line('Exact acceptance rate: '
            .($report['exact_acceptance_rate_pct'] !== null ? $report['exact_acceptance_rate_pct'].'%' : 'n/a'));
        $this->line('Average response time: '
            .($report['average_response_time_seconds'] !== null ? $report['average_response_time_seconds'].'s' : 'n/a'));
        $this->line('');
        $this->line('Order lookup used: '.($report['order_lookup_used_count'] ?? 0).' draft(s)');
        $this->line('Direction lookup used: '.($report['direction_lookup_used_count'] ?? 0).' draft(s)');
        $this->line('');
        $this->line('Top intents:');
        $topIntents = $report['top_intents'] ?? [];
        if ($topIntents === []) {
            $this->line('- (none)');
        } else {
            foreach ($topIntents as $row) {
                $this->line('- '.$row['intent'].': '.$row['count']);
            }
        }

        $this->line('');
        $this->line('Examples (redacted previews):');
        $examples = $report['examples'] ?? [];
        if ($examples === []) {
            $this->line('- (none)');
        } else {
            foreach ($examples as $example) {
                $this->line('- conv='.$example['conversation_id']
                    .' intent='.($example['intent'] ?? 'n/a')
                    .' decision='.$example['operator_decision']
                    .' response='.$example['response_time_seconds'].'s'
                    .' order_lookup='.(($example['order_lookup_used'] ?? false) ? 'yes' : 'no')
                    .' direction_lookup='.(($example['direction_lookup_used'] ?? false) ? 'yes' : 'no'));
                $this->line('  draft: "'.($example['suggestion_preview'] ?? '').'"');
                $this->line('  sent:  "'.($example['operator_reply_preview'] ?? '').'"');
            }
        }

        $this->line('');
        $this->line('Result: '.($report['status'] ?? 'PASS'));

        return self::SUCCESS;
    }
}
