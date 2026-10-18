<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Orders;

use App\Services\Orders\WaitingHandleAge;
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

        $statusInt = (int) $value->status;
        $claimed = $operators !== [];
        $waitingAgeHuman = $statusInt === 3 ? WaitingHandleAge::human($value) : null;
        $waitingAgeSeconds = $statusInt === 3 ? WaitingHandleAge::seconds($value) : null;
        $waitingAgingLevel = $statusInt === 3 ? WaitingHandleAge::agingLevel($value) : null;
        $claimPrompt = $statusInt === 3 ? WaitingHandleAge::claimPrompt($value) : null;
        $paymentSignal = $statusInt === 3 ? WaitingHandleAge::paymentSignal($value) : null;

        if ($claimPrompt !== null) {
            $name = $name.' · '.$claimPrompt;
        }

        // Live Vue list only renders `created` — surface marked-paid age there for status 3.
        $createdDisplay = $statusInt === 3 && $waitingAgeHuman !== null
            ? $waitingAgeHuman.($claimed ? '' : ' · unclaimed')
            : Carbon::parse($value->created_at)->diffForHumans();

        return [
            'auth_id' => $authUserId,
            'in_flow_funds' => $value->in_flow_funds ?? null,
            'amount' => $amount,
            'status_int' => $value->status,
            'id' => $value->id,
            'public_id' => ($value->public_id ?? 0) > 0 ? $value->public_id : $value->id,
            'name' => $name,
            'created' => $createdDisplay,
            'hidden' => false,
            'is_freeze_scam' => (isset($value->task_info) && (int) $value->task_info->is_freeze_scam === 1),
            'payment_in_name' => $paymentInName,
            'operators' => $operators,
            'claimed' => $claimed,
            'waiting_handle_age_human' => $waitingAgeHuman,
            'waiting_handle_age_seconds' => $waitingAgeSeconds,
            'waiting_handle_aging_level' => $waitingAgingLevel,
            'claim_prompt' => $claimPrompt,
            'payment_signal' => $paymentSignal,
            'paid_age_human' => ($statusInt === 7)
                ? \App\Services\Orders\PaidOrderAge::human($value)
                : null,
            'paid_aging_level' => ($statusInt === 7)
                ? \App\Services\Orders\PaidOrderAge::agingLevel($value)
                : null,
            'status_label' => \App\Services\Orders\OperatorStatusVocabulary::adminLabel($statusInt),
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
