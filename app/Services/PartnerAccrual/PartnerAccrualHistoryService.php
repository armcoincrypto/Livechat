<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual;

use App\Enums\PartnerAccrualStatus;
use App\Models\PartnerAccrualPlan;
use App\Models\UserBalanceLog;
use iEXPackages\ReferralSystem\Models\ReferralLog;
use Illuminate\Support\Collection;

/**
 * Partner-facing executed accrual history (own user only).
 * Does not invent ReferralLog rows.
 */
final class PartnerAccrualHistoryService
{
    public function __construct(
        private readonly PartnerAccrualAdminPresenter $presenter = new PartnerAccrualAdminPresenter(),
    ) {}

    /**
     * High-level plan status for partner (no future daily amounts).
     *
     * @return array<string, mixed>|null
     */
    public function partnerPlanVisibility(int $userId): ?array
    {
        if (! config('partner_accrual.enabled')) {
            return null;
        }

        $plan = PartnerAccrualPlan::query()
            ->where('user_id', $userId)
            ->whereIn('status', [
                PartnerAccrualStatus::ACTIVE->value,
                PartnerAccrualStatus::PAUSED->value,
                PartnerAccrualStatus::COMPLETED->value,
            ])
            ->orderByDesc('id')
            ->first();

        if (! $plan) {
            return null;
        }

        return [
            'status' => $plan->status->value,
            'currency_code' => (string) $plan->currency_code,
            'period_start' => $plan->start_date?->toDateString(),
            'period_end' => $plan->end_date?->toDateString(),
            'credited_display' => PartnerAccrualMoneyPresenter::display((string) $plan->credited_amount),
            'total_display' => PartnerAccrualMoneyPresenter::display((string) $plan->total_amount),
            'pending_schedule_visible' => false,
        ];
    }

    /**
     * Executed monthly accrual credits only.
     *
     * @return list<array<string, mixed>>
     */
    public function executedAccrualsForUser(int $userId, int $limit = 50): array
    {
        if (! config('partner_accrual.enabled')) {
            return [];
        }

        return UserBalanceLog::query()
            ->where('id_user', $userId)
            ->where('source', PartnerAccrualCurrency::AUDIT_SOURCE)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (UserBalanceLog $log) {
                $delta = AccrualMoney::of((string) $log->to_balance)
                    ->minus(AccrualMoney::of((string) ($log->from_balance ?? '0')));

                return [
                    'id' => 'accrual-'.$log->id,
                    'type' => 'monthly_partner_accrual',
                    'source_label' => 'Monthly partner accrual',
                    'source_label_ru' => 'Ежемесячное партнёрское начисление',
                    'attributes' => [
                        'status' => 'credited',
                        'created_at' => optional($log->created_at)?->format('d M Y'),
                        'created_at_iso' => optional($log->created_at)?->toIso8601String(),
                        'amount' => PartnerAccrualMoneyPresenter::display($delta),
                        'amount_signed' => '+'.PartnerAccrualMoneyPresenter::display($delta),
                        'currency' => PartnerAccrualCurrency::V1_CODE,
                        'order_id' => null,
                        'reference' => $log->reference,
                    ],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Unified feed: referral commissions + monthly accruals + optional manual markers.
     *
     * @return list<array<string, mixed>>
     */
    public function unifiedHistory(int $userId, int $limit = 50): array
    {
        $items = [];

        $referrals = ReferralLog::query()
            ->where('id_user', $userId)
            ->where('bonus_number', '>', 0)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        foreach ($referrals as $row) {
            $items[] = [
                'id' => 'referral-'.$row->id,
                'type' => 'referral_commission',
                'sort_at' => optional($row->created_at)?->timestamp ?? 0,
                'source_label' => 'Referral commission',
                'source_label_ru' => 'Реферальная комиссия',
                'attributes' => [
                    'status' => (string) ($row->status ?? ''),
                    'created_at' => optional($row->created_at)?->format('d M Y'),
                    'created_at_iso' => optional($row->created_at)?->toIso8601String(),
                    'amount' => PartnerAccrualMoneyPresenter::display((string) $row->bonus_number),
                    'currency' => PartnerAccrualCurrency::V1_CODE,
                    'order_id' => $row->id_task ?? null,
                ],
            ];
        }

        foreach ($this->executedAccrualsForUser($userId, $limit) as $acc) {
            $items[] = [
                'id' => $acc['id'],
                'type' => $acc['type'],
                'sort_at' => isset($acc['attributes']['created_at_iso'])
                    ? (strtotime((string) $acc['attributes']['created_at_iso']) ?: 0)
                    : 0,
                'source_label' => $acc['source_label'],
                'source_label_ru' => $acc['source_label_ru'],
                'attributes' => $acc['attributes'],
            ];
        }

        // Manual adjustments (admin absolute set / ops) when source present
        $manual = UserBalanceLog::query()
            ->where('id_user', $userId)
            ->where(function ($q) {
                $q->where('source', 'admin_absolute_set')
                    ->orWhere('route_type', 1);
            })
            ->where(function ($q) {
                $q->whereNull('source')
                    ->orWhere('source', '!=', PartnerAccrualCurrency::AUDIT_SOURCE);
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        foreach ($manual as $log) {
            if ($log->source === PartnerAccrualCurrency::AUDIT_SOURCE) {
                continue;
            }
            $delta = AccrualMoney::of((string) $log->to_balance)
                ->minus(AccrualMoney::of((string) ($log->from_balance ?? '0')));
            $items[] = [
                'id' => 'manual-'.$log->id,
                'type' => 'manual_adjustment',
                'sort_at' => optional($log->created_at)?->timestamp ?? 0,
                'source_label' => 'Manual adjustment',
                'source_label_ru' => 'Ручная корректировка',
                'attributes' => [
                    'status' => 'adjusted',
                    'created_at' => optional($log->created_at)?->format('d M Y'),
                    'created_at_iso' => optional($log->created_at)?->toIso8601String(),
                    'amount' => PartnerAccrualMoneyPresenter::display($delta),
                    'currency' => PartnerAccrualCurrency::V1_CODE,
                    'order_id' => null,
                ],
            ];
        }

        usort($items, static fn ($a, $b) => ($b['sort_at'] <=> $a['sort_at']));

        return array_slice($items, 0, $limit);
    }

    public function monthlyAccrualCount(int $userId): int
    {
        if (! config('partner_accrual.enabled')) {
            return 0;
        }

        return (int) UserBalanceLog::query()
            ->where('id_user', $userId)
            ->where('source', PartnerAccrualCurrency::AUDIT_SOURCE)
            ->count();
    }
}
