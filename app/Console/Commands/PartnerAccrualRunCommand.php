<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PartnerAccrual\PartnerAccrualRunner;
use Illuminate\Console\Command;

/**
 * Partner accrual runner. Default is dry-run.
 * Apply requires PARTNER_ACCRUAL_ENABLED + PARTNER_ACCRUAL_ALLOW_APPLY + --apply.
 */
final class PartnerAccrualRunCommand extends Command
{
    protected $signature = 'partner-accrual:run
        {--dry-run : Inspect due installments without mutations (default)}
        {--apply : Execute credits (blocked unless feature flags allow)}
        {--limit= : Max installments this run}
        {--plan= : Restrict to plan id}
        {--date= : Business date Y-m-d (defaults to business today)}';

    protected $description = 'Process due partner accrual installments (dry-run by default; fail-closed apply).';

    public function handle(PartnerAccrualRunner $runner): int
    {
        $apply = (bool) $this->option('apply');
        $dryRunFlag = (bool) $this->option('dry-run');

        // Default dry-run when neither flag set; --apply without allow → refuse.
        $dryRun = ! $apply || $dryRunFlag;
        if ($apply) {
            if (! config('partner_accrual.enabled') || ! config('partner_accrual.allow_apply')) {
                $this->error('APPLY_BLOCKED: PARTNER_ACCRUAL_ENABLED and PARTNER_ACCRUAL_ALLOW_APPLY must both be true');

                return self::FAILURE;
            }
            $dryRun = false;
        }

        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $plan = $this->option('plan') !== null ? (int) $this->option('plan') : null;
        $date = $this->option('date') !== null ? (string) $this->option('date') : null;

        $result = $runner->run(
            dryRun: $dryRun,
            limit: $limit,
            planId: $plan,
            businessDate: $date,
        );

        $this->line('BUSINESS_DATE='.$result['business_date']);
        $this->line('BUSINESS_TIMEZONE='.$result['business_timezone']);
        $this->line('DRY_RUN='.($result['dry_run'] ? 'YES' : 'NO'));
        $this->line('DUE_PLANS='.$result['due_plans']);
        $this->line('DUE_INSTALLMENTS='.$result['due_installments']);
        if ($result['dry_run']) {
            $this->line('WOULD_CREDIT_COUNT='.$result['would_credit_count']);
            $this->line('WOULD_CREDIT_TOTAL='.$result['would_credit_total'].' USD');
        } else {
            $this->line('CREDITED_COUNT='.$result['credited_count']);
            $this->line('CREDITED_TOTAL='.$result['credited_total'].' USD');
        }
        $this->line('OVERDUE_REMAINING='.$result['overdue_remaining']);
        $this->line('BALANCE_MUTATIONS='.$result['balance_mutations']);
        $this->line('INSTALLMENT_MUTATIONS='.$result['installment_mutations']);
        $this->line('AUDIT_MUTATIONS='.$result['audit_mutations']);

        foreach ($result['installments'] as $row) {
            $this->line(sprintf(
                'plan_id=%d installment_id=%d user_id=%d scheduled_for=%s amount=%s status=%s eligibility=%s outcome=%s',
                $row['plan_id'],
                $row['installment_id'],
                $row['user_id'],
                $row['scheduled_for'],
                $row['amount'],
                $row['current_status'],
                $row['eligibility'],
                $row['outcome'] ?? ''
            ));
        }

        return self::SUCCESS;
    }
}
