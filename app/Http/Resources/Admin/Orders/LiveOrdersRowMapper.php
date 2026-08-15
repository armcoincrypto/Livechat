<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Orders;

use Illuminate\Support\Carbon;

/**
 * Null-safe liveOrders serializer.
 * HISTORICAL_WITH_TRASHED relations may be present; missing nested catalog is labeled, not faked as empty currency codes when the direction itself is corrupt/missing.
 */
final class LiveOrdersRowMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function map(object $value, int|string|null $authUserId): array
    {
        $direction = $value->direction_exchange ?? null;
        $currency1 = $direction?->currency1;
        $currency2 = $direction?->currency2;
        $code1 = (string) ($currency1?->code_currency?->name ?? '');
        $code2 = (string) ($currency2?->code_currency?->name ?? '');
        $pay1 = (string) ($currency1?->payment?->name ?? '');
        $pay2 = (string) ($currency2?->payment?->name ?? '');

        if ($direction === null) {
            $amount = '#'.($value->id_direction_exchange ?? $value->id).' (архив)';
            $name = $amount;
            $paymentInName = '';
        } else {
            $leftAmt = self::safeInAmount($value);
            $rightAmt = self::safeOutAmount($value);
            $leftCode = $code1 !== '' ? $code1 : '—';
            $rightCode = $code2 !== '' ? $code2 : '—';
            $amount = $leftAmt.' '.$leftCode.' → '.$rightAmt.' '.$rightCode;
            $leftPay = $pay1 !== '' ? $pay1 : 'архив';
            $rightPay = $pay2 !== '' ? $pay2 : 'архив';
            $name = $leftPay.' '.$leftCode.' → '.$rightPay.' '.$rightCode;
            $paymentInName = $pay1;
        }

        $operators = [];
        if (isset($value->task_operators) && count($value->task_operators) > 0) {
            foreach ($value->task_operators as $operator) {
                if ($operator->user === null) {
                    continue;
                }
                $operators[] = [
                    'id' => $operator->user->id,
                    'name' => $operator->user->name,
                ];
            }
        }

        return [
            'auth_id' => $authUserId,
            'in_flow_funds' => $value->in_flow_funds ?? null,
            'amount' => $amount,
            'status_int' => $value->status,
            'id' => $value->id,
            'public_id' => ($value->public_id ?? 0) > 0 ? $value->public_id : $value->id,
            'name' => $name,
            'created' => Carbon::parse($value->created_at)->diffForHumans(),
            'hidden' => false,
            'is_freeze_scam' => (isset($value->task_info) && (int) $value->task_info->is_freeze_scam === 1),
            'payment_in_name' => $paymentInName,
            'operators' => $operators,
        ];
    }

    private static function safeInAmount(object $value): string
    {
        try {
            return (string) display_in_price_auto($value, 'give_price', true, true);
        } catch (\Throwable) {
            return (string) ($value->give_price ?? '');
        }
    }

    private static function safeOutAmount(object $value): string
    {
        try {
            return (string) display_out_price_auto($value, 'receiving_price', false, true);
        } catch (\Throwable) {
            return (string) ($value->receiving_price ?? '');
        }
    }
}
