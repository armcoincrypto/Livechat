<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual;

use App\Enums\PartnerAccrualInstallmentStatus;
use App\Enums\PartnerAccrualStatus;
use App\Models\PartnerAccrualInstallment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Bounded due-installment selection. Oldest first.
 */
final class PartnerAccrualDueQuery
{
    public function __construct(
        private readonly PartnerAccrualBusinessClock $clock = new PartnerAccrualBusinessClock(),
    ) {}

    /**
     * @return Collection<int, PartnerAccrualInstallment>
     */
    public function findDueInstallments(
        ?string $businessDate = null,
        int $limit = 50,
        ?int $planId = null,
    ): Collection {
        $asOf = $businessDate ?? $this->clock->todayDateString();
        $limit = max(1, $limit);

        $q = PartnerAccrualInstallment::query()
            ->select('partner_accrual_installments.*')
            ->join('partner_accrual_plans', 'partner_accrual_plans.id', '=', 'partner_accrual_installments.plan_id')
            ->where('partner_accrual_plans.status', PartnerAccrualStatus::ACTIVE->value)
            ->where('partner_accrual_installments.status', PartnerAccrualInstallmentStatus::PENDING->value)
            ->whereDate('partner_accrual_installments.scheduled_for', '<=', $asOf)
            ->orderBy('partner_accrual_installments.scheduled_for')
            ->orderBy('partner_accrual_installments.id')
            ->limit($limit);

        if ($planId !== null) {
            $q->where('partner_accrual_installments.plan_id', $planId);
        }

        return $q->get();
    }

    public function countOverdue(?string $businessDate = null, ?int $planId = null): int
    {
        $asOf = $businessDate ?? $this->clock->todayDateString();
        $q = PartnerAccrualInstallment::query()
            ->join('partner_accrual_plans', 'partner_accrual_plans.id', '=', 'partner_accrual_installments.plan_id')
            ->where('partner_accrual_plans.status', PartnerAccrualStatus::ACTIVE->value)
            ->where('partner_accrual_installments.status', PartnerAccrualInstallmentStatus::PENDING->value)
            ->whereDate('partner_accrual_installments.scheduled_for', '<=', $asOf);

        if ($planId !== null) {
            $q->where('partner_accrual_installments.plan_id', $planId);
        }

        return (int) $q->count();
    }

    public function userEligible(int $userId): bool
    {
        $user = User::query()->find($userId);
        if (! $user) {
            return false;
        }
        if ($user->banned_at !== null) {
            return false;
        }
        if ((int) ($user->deactivation ?? 0) === 1) {
            return false;
        }

        return true;
    }
}
