<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual;

use App\Enums\PartnerAccrualInstallmentStatus;
use App\Enums\PartnerAccrualStatus;
use App\Models\PartnerAccrualInstallment;
use App\Models\PartnerAccrualPlan;
use App\Models\UserBalanceLog;
use Illuminate\Support\Facades\DB;

/**
 * Privacy-safe operational snapshot for partner accrual.
 */
final class PartnerAccrualObservability
{
    public function __construct(
        private readonly PartnerAccrualBusinessClock $clock = new PartnerAccrualBusinessClock(),
        private readonly PartnerAccrualDueQuery $dueQuery = new PartnerAccrualDueQuery(),
        private readonly PartnerAccrualReconciliationService $reconcile = new PartnerAccrualReconciliationService(),
    ) {}

    /** @return array<string, int|string|null> */
    public function snapshot(): array
    {
        $today = $this->clock->todayDateString();
        $monthStart = $this->clock->today()->startOfMonth()->toDateString();

        $active = PartnerAccrualPlan::query()->where('status', PartnerAccrualStatus::ACTIVE->value)->count();
        $paused = PartnerAccrualPlan::query()->where('status', PartnerAccrualStatus::PAUSED->value)->count();
        $due = $this->dueQuery->countOverdue($today);
        $overdue = $due; // due includes today; overdue metric = due for ops

        $failed24 = PartnerAccrualInstallment::query()
            ->where('status', PartnerAccrualInstallmentStatus::FAILED->value)
            ->where('updated_at', '>=', $this->clock->now()->subDay()->toDateTimeString())
            ->count();

        $oldest = PartnerAccrualInstallment::query()
            ->join('partner_accrual_plans', 'partner_accrual_plans.id', '=', 'partner_accrual_installments.plan_id')
            ->where('partner_accrual_plans.status', PartnerAccrualStatus::ACTIVE->value)
            ->where('partner_accrual_installments.status', PartnerAccrualInstallmentStatus::PENDING->value)
            ->whereDate('partner_accrual_installments.scheduled_for', '<=', $today)
            ->orderBy('partner_accrual_installments.scheduled_for')
            ->value('partner_accrual_installments.scheduled_for');

        $creditedToday = UserBalanceLog::query()
            ->where('source', PartnerAccrualCurrency::AUDIT_SOURCE)
            ->whereDate('created_at', $today)
            ->count();

        $creditedMonth = UserBalanceLog::query()
            ->where('source', PartnerAccrualCurrency::AUDIT_SOURCE)
            ->whereDate('created_at', '>=', $monthStart)
            ->count();

        $mismatch = 0;
        $planIds = PartnerAccrualPlan::query()
            ->whereIn('status', [
                PartnerAccrualStatus::ACTIVE->value,
                PartnerAccrualStatus::PAUSED->value,
                PartnerAccrualStatus::COMPLETED->value,
                PartnerAccrualStatus::ERROR->value,
            ])
            ->limit(200)
            ->pluck('id');
        foreach ($planIds as $id) {
            $r = $this->reconcile->reconcilePlan((int) $id);
            if ($r['status'] !== PartnerAccrualReconciliationService::CONSISTENT) {
                $mismatch++;
            }
        }

        return [
            'business_date' => $today,
            'business_timezone' => $this->clock->timezone(),
            'feature_enabled' => (bool) config('partner_accrual.enabled', false),
            'scheduler_enabled' => (bool) config('partner_accrual.scheduler_enabled', false),
            'active_plans' => $active,
            'paused_plans' => $paused,
            'due_installments' => $due,
            'overdue_installments' => $overdue,
            'failed_installments_24h' => $failed24,
            'oldest_overdue' => $oldest ? (string) $oldest : null,
            'credited_today' => $creditedToday,
            'credited_this_month' => $creditedMonth,
            'reconciliation_mismatches' => $mismatch,
        ];
    }
}
