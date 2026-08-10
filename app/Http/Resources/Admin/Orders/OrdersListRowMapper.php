<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Orders;

use App\Presenters\SelectedFeesPresenter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Shared list-row mapper for admin orders (live + paginated).
 * Must tolerate soft-deleted directions and missing operator users.
 */
final class OrdersListRowMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function map(object $item): array
    {
        $currentUserId = Auth::id();
        $operators = [];

        if (isset($item->task_operators) && count($item->task_operators) > 0) {
            $operators = $item->task_operators
                ->filter(static fn ($value) => $value->user !== null)
                ->map(static function ($value) use ($currentUserId) {
                    $isCurrentUser = $value->user->id === $currentUserId;

                    return [
                        'user' => $value->user,
                        'name' => $value->user->name,
                        'avatar' => Str::upper(Str::substr((string) ($value->user->name ?? ''), 0, 1)),
                        'is_current_user' => $isCurrentUser,
                        'created_at' => $value->created_at?->format('c'),
                    ];
                })
                ->values();
        }

        $sfSnapshot = is_array($item->meta?->selected_fees) ? $item->meta->selected_fees : [];
        $sfFlat = SelectedFeesPresenter::flatten($sfSnapshot);

        $direction = $item->direction_exchange;
        $currency1 = $direction?->currency1;
        $currency2 = $direction?->currency2;

        return [
            'id' => $item->id,
            'public_id' => $item->public_id,
            'display_id' => self::safeDisplayId($item),
            'attributes' => [
                'user' => [
                    'id' => $item->user->id ?? null,
                    'name' => $item->user->name ?? null,
                    'email' => $item->user->email ?? null,
                    'language' => $item->meta?->user_flag ?? null,
                ],
                'operators' => $operators,
                'type_rate' => $item->type_rate,
                'is_type_rate' => $item->is_type_rate,
                'course_display' => $item->course_display,
                'is_request_payment_type' => $item->is_request_payment_type,
                'city_name' => $item->task_info?->city_name ?? null,
                'country_name' => $item->task_info?->country_name ?? null,
                'direction' => [
                    'id' => $direction?->id,
                    'id_currency1' => $direction?->id_currency1,
                    'id_currency2' => $direction?->id_currency2,
                    'name' => $direction?->tech_name ?? ('#'.$item->id_direction_exchange.' (архив)'),
                    'is_archived' => $direction !== null && method_exists($direction, 'trashed') && $direction->trashed(),
                ],
                'currency_in' => [
                    'id' => $direction?->id_currency1,
                    'name' => self::safeCurrencyLabel($item, true, $direction),
                    'code_name' => $currency1?->code_currency?->name ?? '',
                    'amount' => $item->give_price_with_comm,
                    'icon' => (($__cinLogo = $currency1?->payment?->logo ?? '') !== '')
                        ? '/storage/payment_systems/'.$__cinLogo
                        : '',
                ],
                'currency_out' => [
                    'id' => $direction?->id_currency2,
                    'name' => self::safeCurrencyLabel($item, false, $direction),
                    'code_name' => $currency2?->code_currency?->name ?? '',
                    'amount' => $item->receiving_price_with_comm,
                    'icon' => (($__coutLogo = $currency2?->payment?->logo ?? '') !== '')
                        ? '/storage/payment_systems/'.$__coutLogo
                        : '',
                ],
                'selected_fees' => $sfFlat,
                'status' => $item->status,
                'status_name' => $item->task_status?->name ?? null,
                'is_trashed' => method_exists($item, 'trashed') ? $item->trashed() : false,
                'queue_status' => $item->queue_status ?? null,
                'created_at' => $item->created_at?->format('c'),
                'created_at_human' => $item->created_at?->diffForHumans(),
                'updated_at' => $item->updated_at?->translatedFormat('d M Y H:i'),
                'updated_at_human' => $item->updated_at?->diffForHumans(),
            ],
        ];
    }

    private static function safeDisplayId(object $item): string|int|null
    {
        try {
            if ($item instanceof \App\Models\Task) {
                return current_order_id($item);
            }
        } catch (\Throwable) {
            // fall through
        }

        return $item->public_id ?? $item->id ?? null;
    }

    private static function safeCurrencyLabel(object $item, bool $in, ?object $direction): string
    {
        if ($direction === null) {
            return '—';
        }

        try {
            return $in ? (string) currency_in($item, true) : (string) currency_out($item, true);
        } catch (\Throwable) {
            $cur = $in ? $direction->currency1 : $direction->currency2;

            return (string) ($cur?->code_currency?->name ?? $direction->tech_name ?? '—');
        }
    }
}
