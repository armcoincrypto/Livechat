<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual;

use App\Enums\PartnerAccrualStatus;
use App\Models\PartnerAccrualPlan;
use App\Services\PartnerAccrual\Exceptions\CurrencyNotSupported;
use App\Services\PartnerAccrual\Exceptions\OverlappingPlanExists;
use Illuminate\Support\Facades\DB;

/**
 * Application-level overlap guard:
 * ONE ACTIVE|PAUSED|DRAFT plan per user per currency per overlapping period.
 */
final class PartnerAccrualPlanGuard
{
    /**
     * @param  array{
     *   user_id: int,
     *   currency_code: string,
     *   currency_code_id?: int|null,
     *   total_amount: string,
     *   start_date: string,
     *   end_date: string,
     *   status?: string,
     *   created_by_admin_id?: int|null,
     *   metadata?: array|null
     * }  $attributes
     */
    public function createPlanExclusive(array $attributes): PartnerAccrualPlan
    {
        $currency = strtoupper(trim((string) $attributes['currency_code']));
        PartnerAccrualCurrency::assertSupported($currency);

        return DB::transaction(function () use ($attributes, $currency) {
            $userId = (int) $attributes['user_id'];
            $start = (string) $attributes['start_date'];
            $end = (string) $attributes['end_date'];

            // Serialize plan creation per user (lock any existing plan row for user).
            PartnerAccrualPlan::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->get();

            $blocking = array_map(
                static fn (PartnerAccrualStatus $s) => $s->value,
                PartnerAccrualStatus::blockingOverlap()
            );

            $overlap = PartnerAccrualPlan::query()
                ->where('user_id', $userId)
                ->where('currency_code', $currency)
                ->whereIn('status', $blocking)
                ->where('start_date', '<=', $end)
                ->where('end_date', '>=', $start)
                ->exists();

            if ($overlap) {
                throw OverlappingPlanExists::forUser($userId, $currency);
            }

            return PartnerAccrualPlan::create([
                'user_id' => $userId,
                'currency_code' => $currency,
                'currency_code_id' => $attributes['currency_code_id'] ?? null,
                'total_amount' => AccrualMoney::scale8(AccrualMoney::of($attributes['total_amount'])),
                'credited_amount' => AccrualMoney::scale8(AccrualMoney::zero()),
                'start_date' => $start,
                'end_date' => $end,
                'status' => $attributes['status'] ?? PartnerAccrualStatus::DRAFT->value,
                'created_by_admin_id' => $attributes['created_by_admin_id'] ?? null,
                'metadata' => $attributes['metadata'] ?? null,
                'version' => 1,
            ]);
        });
    }

    public function rejectUsdtPlanCreation(string $currencyCode): void
    {
        $code = strtoupper(trim($currencyCode));
        if ($code === 'USDT' || $code !== PartnerAccrualCurrency::V1_CODE) {
            throw CurrencyNotSupported::forCode($code);
        }
    }
}
