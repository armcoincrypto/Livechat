<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Console;

use iEXPackages\SupportChat\Services\SupportAiLearningWeeklyAuditService;
use Illuminate\Console\Command;

final class SupportChatAiLearningWeeklyAuditCommand extends Command
{
    protected $signature = 'support-chat:ai-learning-weekly-audit
                            {--days=7 : Lookback window in days}
                            {--json : Output machine-readable JSON}';

    protected $description = 'Weekly read-only audit of Phase A AI learning telemetry and milestone readiness.';

    public function handle(SupportAiLearningWeeklyAuditService $audit): int
    {
        if (! $audit->isEnabled()) {
            $this->warn('Weekly audit disabled (SUPPORT_AI_WEEKLY_AUDIT_ENABLED=0).');

            return self::SUCCESS;
        }

        $days = max(1, min(365, (int) $this->option('days')));
        $report = $audit->buildReport($days);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if (! ($report['telemetry_available'] ?? false)) {
            $this->error($report['message'] ?? 'Telemetry unavailable.');

            return self::FAILURE;
        }

        $this->line('AI Learning Weekly Audit (Phase A)');
        $this->line('');
        $this->line('Period: '.$days.' days (since '.($report['period_since'] ?? '—').')');
        $this->line('Phase A status: '.($report['phase_a_status'] ?? 'WAITING'));
        $this->line('');
        $this->line('Acceptance (period):');
        $this->printUsageBlock($report['acceptance']['period'] ?? []);
        $this->line('');
        $this->line('Acceptance (all time):');
        $this->printUsageBlock($report['acceptance']['all_time'] ?? []);
        $this->line('');
        $this->line('Outcomes (period):');
        $this->printOutcomeBlock($report['outcomes']['period'] ?? []);
        $this->line('');
        $this->line('Outcomes (all time):');
        $this->printOutcomeBlock($report['outcomes']['all_time'] ?? []);
        $this->line('');
        $this->line('Matching (period):');
        $matching = $report['matching'] ?? [];
        $this->line('- Usage records: '.($matching['total_usage_records'] ?? 0));
        $this->line('- Unknown rate: '.($matching['unknown_rate_pct'] !== null ? $matching['unknown_rate_pct'].'%' : 'n/a'));
        $this->line('');
        $this->line('Candidates:');
        $candidates = $report['candidates'] ?? [];
        $this->line('- Total: '.($candidates['total'] ?? 0));
        $this->line('- Quarantined: '.($candidates['quarantined'] ?? 0));
        $this->line('- Ready for promotion: '.($candidates['ready_for_promotion'] ?? 0));
        $this->line('');
        $this->line('Next milestone gates (all time):');
        foreach ($report['milestones']['gates'] ?? [] as $name => $gate) {
            $met = ! empty($gate['met']) ? 'MET' : 'NOT MET';
            $this->line(sprintf(
                '- %s: %d / %d (%s)',
                str_replace('_', ' ', $name),
                (int) ($gate['current'] ?? 0),
                (int) ($gate['required'] ?? 0),
                $met,
            ));
        }
        $this->line('');
        $this->line($report['message'] ?? '');
        $this->line('');
        $this->line('Result: PASS');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function printUsageBlock(array $block): void
    {
        $this->line('- Total: '.($block['total'] ?? 0));
        $this->line('- Accepted exact: '.($block['accepted_exact'] ?? 0));
        $this->line('- Accepted modified: '.($block['accepted_modified'] ?? 0));
        $this->line('- Ignored: '.($block['ignored'] ?? 0));
        $this->line('- Unknown: '.($block['unknown'] ?? 0));
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function printOutcomeBlock(array $block): void
    {
        $this->line('- Total: '.($block['total'] ?? 0));
        $this->line('- Resolved: '.($block['resolved'] ?? 0));
        $this->line('- Pending: '.($block['pending'] ?? 0));
        $this->line('- Failed: '.($block['failed'] ?? 0));
        $this->line('- Reopened: '.($block['reopened'] ?? 0));
    }
}
