<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PartnerAccrualInstallment;
use App\Models\PartnerAccrualPlan;
use App\Models\UserBalanceLog;
use App\Enums\PartnerAccrualInstallmentStatus;
use App\Services\PartnerAccrual\AccrualMoney;
use App\Services\PartnerAccrual\PartnerAccrualCurrency;
use App\Services\PartnerAccrual\PartnerAccrualReconciliationService;
use Illuminate\Console\Command;

/** Read-only plan inspection. No auto-repair. */
final class PartnerAccrualInspectCommand extends Command
{
    protected $signature = 'partner-accrual:inspect {plan : Plan id}';

    protected $description = 'Read-only partner accrual plan reconciliation inspect.';

    public function handle(PartnerAccrualReconciliationService $reconcile): int
    {
        $planId = (int) $this->argument('plan');
        $plan = PartnerAccrualPlan::query()->find($planId);
        if (! $plan) {
            $this->error('PLAN_NOT_FOUND');

            return self::FAILURE;
        }

        $executedSum = AccrualMoney::zero();
        $pendingSum = AccrualMoney::zero();
        foreach (PartnerAccrualInstallment::query()->where('plan_id', $planId)->get() as $row) {
            if ($row->status === PartnerAccrualInstallmentStatus::CREDITED) {
                $executedSum = $executedSum->plus(AccrualMoney::of($row->amount));
            }
            if ($row->status === PartnerAccrualInstallmentStatus::PENDING
                || $row->status === PartnerAccrualInstallmentStatus::FAILED) {
                $pendingSum = $pendingSum->plus(AccrualMoney::of($row->amount));
            }
        }

        $auditLogs = UserBalanceLog::query()
            ->where('source', PartnerAccrualCurrency::AUDIT_SOURCE)
            ->where('reference', 'like', 'partner_accrual_installment:%')
            ->whereIn('id', PartnerAccrualInstallment::query()->where('plan_id', $planId)->whereNotNull('balance_log_id')->pluck('balance_log_id'))
            ->get();

        $auditSum = AccrualMoney::zero();
        foreach ($auditLogs as $log) {
            $delta = AccrualMoney::of((string) $log->to_balance)->minus(AccrualMoney::of((string) $log->from_balance));
            $auditSum = $auditSum->plus($delta);
        }

        $recon = $reconcile->reconcilePlan($planId);

        $this->line('PLAN_TOTAL='.AccrualMoney::scale8(AccrualMoney::of($plan->total_amount)));
        $this->line('PLAN_CREDITED='.AccrualMoney::scale8(AccrualMoney::of($plan->credited_amount)));
        $this->line('EXECUTED_INSTALLMENT_SUM='.AccrualMoney::scale8($executedSum));
        $this->line('PENDING_SUM='.AccrualMoney::scale8($pendingSum));
        $this->line('AUDIT_SUM='.AccrualMoney::scale8($auditSum));
        $this->line('BALANCE_LOG_COUNT='.$auditLogs->count());
        $this->line('STATUS='.$plan->status->value);
        $this->line('RECONCILIATION='.$recon['status']);

        return self::SUCCESS;
    }
}
