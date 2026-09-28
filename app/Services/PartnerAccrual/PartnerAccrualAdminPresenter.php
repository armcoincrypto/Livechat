<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual;

use App\Enums\PartnerAccrualInstallmentStatus;
use App\Enums\PartnerAccrualStatus;
use App\Models\PartnerAccrualInstallment;
use App\Models\PartnerAccrualPlan;
use App\Models\PartnerAccrualPlanEvent;
use App\Models\User;
use App\Models\UserBalance;

/**
 * Admin/partner read DTOs — backend-owned money strings.
 */
final class PartnerAccrualAdminPresenter
{
    public function __construct(
        private readonly PartnerAccrualReconciliationService $reconcile = new PartnerAccrualReconciliationService(),
        private readonly PartnerAccrualDueQuery $dueQuery = new PartnerAccrualDueQuery(),
        private readonly PartnerAccrualBusinessClock $clock = new PartnerAccrualBusinessClock(),
    ) {}

    public function featureEnabled(): bool
    {
        return (bool) config('partner_accrual.enabled', false);
    }

    /** @return array<string, mixed> */
    public function userSummary(int $userId): array
    {
        $user = User::query()->findOrFail($userId);
        $eligible = $this->dueQuery->userEligible($userId);
        $balance = UserBalance::query()->where('id_user', $userId)->first();
        $plan = PartnerAccrualPlan::query()
            ->where('user_id', $userId)
            ->whereIn('status', [
                PartnerAccrualStatus::ACTIVE->value,
                PartnerAccrualStatus::PAUSED->value,
                PartnerAccrualStatus::DRAFT->value,
            ])
            ->orderByDesc('id')
            ->first();

        $out = [
            'feature_enabled' => $this->featureEnabled(),
            'user_id' => $userId,
            'user_eligibility' => [
                'eligible' => $eligible,
                'banned' => $user->banned_at !== null,
                'deactivated' => (int) ($user->deactivation ?? 0) === 1,
                'label' => $user->banned_at !== null
                    ? 'Banned'
                    : ((int) ($user->deactivation ?? 0) === 1 ? 'Deactivated' : 'Active'),
            ],
            'current_partner_balance' => [
                'amount' => PartnerAccrualMoneyPresenter::storage((string) ($balance->balance ?? '0')),
                'amount_display' => PartnerAccrualMoneyPresenter::display((string) ($balance->balance ?? '0')),
                'currency' => PartnerAccrualCurrency::V1_CODE,
            ],
            'manual_balance_warning' => 'Ручные изменения баланса не меняют расписание и учёт ежемесячного начисления.',
            'has_plan' => $plan !== null,
            'plan' => $plan ? $this->planSummary($plan) : null,
            'message' => $plan ? null : 'Нет активного ежемесячного начисления',
        ];

        return $out;
    }

    /** @return array<string, mixed> */
    public function planSummary(PartnerAccrualPlan $plan): array
    {
        $total = PartnerAccrualMoneyPresenter::storage((string) $plan->total_amount);
        $credited = PartnerAccrualMoneyPresenter::storage((string) $plan->credited_amount);
        $remainingNeg = PartnerAccrualMoneyPresenter::remainingNegative($total, $credited);
        $recon = $this->reconcile->reconcilePlan((int) $plan->id);

        $next = PartnerAccrualInstallment::query()
            ->where('plan_id', $plan->id)
            ->where('status', PartnerAccrualInstallmentStatus::PENDING->value)
            ->orderBy('scheduled_for')
            ->orderBy('sequence')
            ->first();

        $overdue = 0;
        if ($plan->status === PartnerAccrualStatus::ACTIVE) {
            $overdue = PartnerAccrualInstallment::query()
                ->where('plan_id', $plan->id)
                ->where('status', PartnerAccrualInstallmentStatus::PENDING->value)
                ->whereDate('scheduled_for', '<=', $this->clock->todayDateString())
                ->count();
        }

        $events = PartnerAccrualPlanEvent::query()
            ->where('plan_id', $plan->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (PartnerAccrualPlanEvent $e) => [
                'event_type' => $e->event_type,
                'actor_admin_id' => $e->actor_admin_id,
                'created_at' => optional($e->created_at)?->toIso8601String(),
                'payload' => $e->payload,
            ])
            ->all();

        return [
            'id' => (int) $plan->id,
            'user_id' => (int) $plan->user_id,
            'status' => $plan->status->value,
            'currency_code' => (string) $plan->currency_code,
            'total_amount' => $total,
            'total_display' => PartnerAccrualMoneyPresenter::display($total),
            'credited_amount' => $credited,
            'credited_display' => PartnerAccrualMoneyPresenter::display($credited),
            'remaining_amount' => PartnerAccrualMoneyPresenter::storage(
                AccrualMoney::of($total)->minus(AccrualMoney::of($credited))->isNegative()
                    ? AccrualMoney::zero()
                    : AccrualMoney::of($total)->minus(AccrualMoney::of($credited))
            ),
            'remaining_display' => PartnerAccrualMoneyPresenter::remaining($total, $credited),
            'remaining_negative' => $remainingNeg,
            'start_date' => $plan->start_date?->toDateString(),
            'end_date' => $plan->end_date?->toDateString(),
            'created_by_admin_id' => $plan->created_by_admin_id,
            'created_at' => optional($plan->created_at)?->toIso8601String(),
            'activated_at' => optional($plan->activated_at)?->toIso8601String(),
            'paused_at' => optional($plan->paused_at)?->toIso8601String(),
            'cancelled_at' => optional($plan->cancelled_at)?->toIso8601String(),
            'completed_at' => optional($plan->completed_at)?->toIso8601String(),
            'next_installment' => $next ? [
                'id' => (int) $next->id,
                'scheduled_for' => $next->scheduled_for?->toDateString(),
                'amount' => PartnerAccrualMoneyPresenter::storage((string) $next->amount),
                'amount_display' => PartnerAccrualMoneyPresenter::display((string) $next->amount),
            ] : null,
            'overdue_installments' => $overdue,
            'progress_percent' => $this->progressPercent($total, $credited),
            'reconciliation' => [
                'status' => $recon['status'],
                'ok' => $recon['status'] === PartnerAccrualReconciliationService::CONSISTENT,
                'label' => $recon['status'] === PartnerAccrualReconciliationService::CONSISTENT
                    ? 'OK'
                    : 'Обнаружено расхождение — требуется ручная проверка',
                'plan_total' => $recon['plan_total'],
                'sum_credited_installments' => $recon['sum_credited_installments'],
                'plan_credited_amount' => $recon['plan_credited_amount'],
                'details' => $recon['details'],
                'auto_repair' => false,
            ],
            'events' => $events,
            'edit_policy' => 'NO_EDIT_AFTER_ACTIVATION',
        ];
    }

    /** @return array<string, mixed> */
    public function planDetail(PartnerAccrualPlan $plan): array
    {
        $summary = $this->planSummary($plan);
        $installments = PartnerAccrualInstallment::query()
            ->where('plan_id', $plan->id)
            ->orderBy('sequence')
            ->get()
            ->map(function (PartnerAccrualInstallment $i) {
                return [
                    'id' => (int) $i->id,
                    'sequence' => (int) $i->sequence,
                    'scheduled_for' => $i->scheduled_for?->toDateString(),
                    'amount' => PartnerAccrualMoneyPresenter::storage((string) $i->amount),
                    'amount_display' => PartnerAccrualMoneyPresenter::display((string) $i->amount),
                    'status' => $i->status->value,
                    'executed_at' => optional($i->executed_at)?->toIso8601String(),
                    'attempt_count' => (int) $i->attempt_count,
                    'last_error' => $i->last_error,
                    'audit_reference' => $i->status === PartnerAccrualInstallmentStatus::CREDITED
                        ? PartnerAccrualCurrency::installmentReference((int) $i->id)
                        : null,
                ];
            })
            ->all();

        $summary['installments'] = $installments;
        $summary['installment_count'] = count($installments);

        return $summary;
    }

    /** @param array<string, mixed> $preview */
    public function formatPreview(array $preview): array
    {
        $schedule = [];
        $cumulative = AccrualMoney::zero();
        $total = AccrualMoney::of($preview['total_amount']);
        foreach ($preview['schedule'] as $row) {
            $amt = AccrualMoney::of($row['amount']);
            $cumulative = $cumulative->plus($amt);
            $schedule[] = [
                'sequence' => $row['sequence'],
                'scheduled_for' => $row['scheduled_for'],
                'amount' => PartnerAccrualMoneyPresenter::storage($amt),
                'amount_display' => PartnerAccrualMoneyPresenter::display($amt),
                'cumulative_display' => PartnerAccrualMoneyPresenter::display($cumulative),
                'remaining_display' => PartnerAccrualMoneyPresenter::display($total->minus($cumulative)),
            ];
        }

        return [
            'currency_code' => $preview['currency_code'],
            'total_amount' => PartnerAccrualMoneyPresenter::storage($preview['total_amount']),
            'total_display' => PartnerAccrualMoneyPresenter::display($preview['total_amount']),
            'start_date' => $preview['schedule'][0]['scheduled_for'] ?? null,
            'end_date' => $preview['schedule'][count($preview['schedule']) - 1]['scheduled_for'] ?? null,
            'installment_count' => $preview['installment_count'],
            'average_display' => PartnerAccrualMoneyPresenter::display($preview['average']),
            'min_display' => PartnerAccrualMoneyPresenter::display($preview['min']),
            'max_display' => PartnerAccrualMoneyPresenter::display($preview['max']),
            'sum' => PartnerAccrualMoneyPresenter::storage($preview['sum']),
            'sum_display' => PartnerAccrualMoneyPresenter::display($preview['sum']),
            'exact_sum_ok' => AccrualMoney::eq(AccrualMoney::of($preview['sum']), AccrualMoney::of($preview['total_amount'])),
            'schedule' => $schedule,
            'schedule_seed' => (string) ($preview['schedule_seed'] ?? ''),
            'mutates_db' => false,
            'frontend_authoritative_money' => false,
        ];
    }

    private function progressPercent(string $total, string $credited): string
    {
        $t = AccrualMoney::of($total);
        if (AccrualMoney::cmp($t, AccrualMoney::zero()) <= 0) {
            return '0.00';
        }
        $pct = AccrualMoney::of($credited)
            ->multipliedBy(AccrualMoney::of('100'))
            ->dividedBy($t, 2, \Brick\Math\RoundingMode::HALF_UP);

        return PartnerAccrualMoneyPresenter::display($pct);
    }
}
