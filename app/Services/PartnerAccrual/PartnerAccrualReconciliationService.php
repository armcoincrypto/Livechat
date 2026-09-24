<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual;

use App\Enums\PartnerAccrualInstallmentStatus;
use App\Enums\PartnerAccrualStatus;
use App\Models\PartnerAccrualInstallment;
use App\Models\PartnerAccrualPlan;
use App\Models\UserBalanceLog;

/**
 * Read-only reconciliation. Never auto-repairs.
 */
final class PartnerAccrualReconciliationService
{
    public const CONSISTENT = 'CONSISTENT';

    public const MISMATCH = 'MISMATCH';

    public const MISSING_AUDIT = 'MISSING_AUDIT';

    public const OVER_CREDIT = 'OVER_CREDIT';

    /**
     * @return array{
     *   status: string,
     *   plan_id: int,
     *   plan_total: string,
     *   plan_credited_amount: string,
     *   sum_credited_installments: string,
     *   credited_installment_count: int,
     *   missing_audit_installment_ids: list<int>,
     *   details: list<string>
     * }
     */
    public function reconcilePlan(int $planId): array
    {
        $plan = PartnerAccrualPlan::query()->findOrFail($planId);
        $total = AccrualMoney::of($plan->total_amount);
        $planCredited = AccrualMoney::of($plan->credited_amount);

        $creditedInstallments = PartnerAccrualInstallment::query()
            ->where('plan_id', $planId)
            ->where('status', PartnerAccrualInstallmentStatus::CREDITED->value)
            ->orderBy('sequence')
            ->get();

        $sum = AccrualMoney::zero();
        $missingAudit = [];
        $details = [];

        foreach ($creditedInstallments as $row) {
            $sum = $sum->plus(AccrualMoney::of($row->amount));
            $ref = PartnerAccrualCurrency::installmentReference((int) $row->id);
            $audit = null;
            if ($row->balance_log_id) {
                $audit = UserBalanceLog::query()->find($row->balance_log_id);
            }
            if (! $audit) {
                $audit = UserBalanceLog::query()->where('reference', $ref)->first();
            }
            if (! $audit) {
                $missingAudit[] = (int) $row->id;
            }
        }

        $status = self::CONSISTENT;

        if ($missingAudit !== []) {
            $status = self::MISSING_AUDIT;
            $details[] = 'CREDITED installments missing user_balance_log: '.implode(',', $missingAudit);
        }

        if (! AccrualMoney::eq($sum, $planCredited)) {
            $status = self::MISMATCH;
            $details[] = sprintf(
                'SUM(CREDITED installments)=%s != plan.credited_amount=%s',
                AccrualMoney::scale8($sum),
                AccrualMoney::scale8($planCredited)
            );
        }

        if (AccrualMoney::cmp($planCredited, $total) > 0 || AccrualMoney::cmp($sum, $total) > 0) {
            $status = self::OVER_CREDIT;
            $details[] = sprintf(
                'over-credit: plan_credited=%s sum=%s total=%s',
                AccrualMoney::scale8($planCredited),
                AccrualMoney::scale8($sum),
                AccrualMoney::scale8($total)
            );
        }

        if (
            $plan->status === PartnerAccrualStatus::COMPLETED
            && ! AccrualMoney::eq($sum, $total)
        ) {
            $status = self::MISMATCH;
            $details[] = 'COMPLETED plan but SUM(CREDITED) != total_amount';
        }

        return [
            'status' => $status,
            'plan_id' => (int) $plan->id,
            'plan_total' => AccrualMoney::scale8($total),
            'plan_credited_amount' => AccrualMoney::scale8($planCredited),
            'sum_credited_installments' => AccrualMoney::scale8($sum),
            'credited_installment_count' => $creditedInstallments->count(),
            'missing_audit_installment_ids' => $missingAudit,
            'details' => $details,
            'auto_repair' => false,
        ];
    }
}
