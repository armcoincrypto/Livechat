<?php

declare(strict_types=1);

namespace iEXPackages\Order\Bindings;

use App\Http\Resources\UserResource;
use App\Models\Currency;
use App\Models\Task;
use Illuminate\Support\Carbon;

trait ManagerArray
{
    /**
     * Формирует дополнительные параметры при создании новой заявки.
     *
     * @param Task $order
     * @return array
     */
    public function customArrayForCreate(Task $order): array
    {
        // Побочный эффект лучше убрать в сервис/обсервер. Если пока оставляете — защитите:
        if ($order->is_from_verification_card && !$order->is_wallet_issued) {
            try {
                $order->update(['is_wallet_issued' => true]);
            } catch (\Throwable $e) {
                // логирование по желанию
            }
        }

        $clientIdType = (int) iEXSetting('client_id_type_for_order');

        return [
            'id' => (string) ($clientIdType === 1 ? $order->public_id : $order->id),
            'order_id' => $order->id,
            'type' => 'order',
            'attributes' => $this->buildAttributes($order),
            'relationships' => [
                'user' => $this->buildUserRelationship($order),
                'income_payment_system' => [
                    'data' => $this->buildPaymentSystem($this->getInCurrency(), 'in'),
                ],
                'outcome_payment_system' => [
                    'data' => $this->buildPaymentSystem($this->getOutCurrency(), 'out'),
                ],
            ],
        ];
    }

    /**
     * Формирует атрибуты заявки.
     *
     * @param Task $order
     * @return array
     */
    protected function buildAttributes(Task $order): array
    {
        $inCurrency = $this->getInCurrency();
        $outCurrency = $this->getOutCurrency();

        // Флаг, что пользователь уже прошёл верификацию личности (is_verify_account = 1)
        $identityVerified = isset($this->user) && (int) ($this->user->is_verify_account ?? 0) === 1;

        $identityFromFlow = (int) ($this->is_from_identity_verification ?? 0);

        if (! $identityVerified && $identityFromFlow === 1) {
            $identityStatus = 1;
        } else {
            $identityStatus = 0;
        }

        // Статус верификации карты: 0 — обычная заявка, 1 — из сценария проверки карты
        $cardVerificationStatus = (int) ($this->is_from_verification_card ?? 0);

        $attributes = [
            'public_id' => (string)$order->public_id,
            'created_at' => Carbon::parse($order->created_at)->toIso8601String(),
            'income_account' => $order->from_shot,
            'outcome_account' => $order->to_shot,
            'income_amount' => [
                'amount' => (string) $order->give_price,
                'name' => ($inCurrency->payment?->name ?? ''),
                'currency' => (string) ($inCurrency->code_currency?->name ?? ''),
                'number_format' => $inCurrency->number_format,
            ],
            'outcome_amount' => [
                'amount' => (string) $order->receiving_price,
                'name' => ($outCurrency->payment?->name ?? ''),
                'currency' => (string) ($outCurrency->code_currency?->name ?? ''),
                'number_format' => $outCurrency->number_format,
            ],
            'status' => $order->status === 4 ? 'success' : 'waiting',
            'verification_status'          => $cardVerificationStatus,
            'identity_verification_status' => $identityStatus,
            'instructions' => $this->directionId?->instructions,
            'other_info' => [
                'text_order_confirm' => optional($order->direction_exchange)->text_order_confirm,
                'order_button_i_confirm' => optional($order->direction_exchange)->order_button_i_confirm,
            ],

            'income_fields' => $order->tasks_fields_currency_in
                ->whereNotNull('field_name')
                ->whereNotNull('field_value')
                ->where('field_name', '!=', '')
                ->where('field_value', '!=', '')
                ->map(fn($f) => [
                    'field_name' => $f->field_name,
                    'field_value' => $f->field_value,
                ])
                ->values(),

            'outcome_fields' => $order->tasks_fields_currency_out
                ->whereNotNull('field_name')
                ->whereNotNull('field_value')
                ->where('field_name', '!=', '')
                ->where('field_value', '!=', '')
                ->map(fn($f) => [
                    'field_name' => $f->field_name,
                    'field_value' => $f->field_value,
                ])
                ->values(),

            'course_display' => $order->course_display,
        ];

        $country = $order->task_info?->country_name;
        $city    = $order->task_info?->city_name;
        if (!empty($country) && !empty($city)) {
            $attributes['cities_value'] = "{$country} - {$city}";
        }

        return $attributes;
    }

    /**
     * Формирует данные о пользователе.
     *
     * @param Task $order
     * @return UserResource|array
     */
    protected function buildUserRelationship(Task $order): UserResource|array
    {
        if (auth()->check()) {
            return new UserResource(
                $order->relationLoaded('user') ? $order->user : $order->user()->first()
            );
        }

        return [
            'type' => 'user',
            'attributes' => [
                'is_auth' => false,
            ],
        ];
    }

    /**
     * Формирует данные платежной системы.
     *
     * @param Currency $currency
     * @param string $direction
     * @return array
     */
    protected function buildPaymentSystem(Currency $currency, string $direction): array
    {
        $iconUrl = asset('storage/payment_systems/' . optional($currency->payment)->logo);

        $fieldKey = $direction === 'in' ? 'field_name_from' : 'field_name_to';

        $payment   = $currency->payment;
        $codeModel = $currency->code_currency;

        $paymentName = (string) ($payment?->name ?? '');
        $isoCode     = (string) ($codeModel?->name ?? '');

        $displayName = $currency->visible_code_currency === 1
            ? trim($paymentName . ' ' . $isoCode)
            : $paymentName;

        return [
            'id' => (int) $currency->id,
            'type' => 'payment_system',
            'attributes' => [
                'name' => $displayName,
                'payment_system' => $paymentName,
                'letter_cod' => (string) $currency->designation_xml,
                'currency_iso_code' => $isoCode,
                'icon_url' => $iconUrl,
                $fieldKey => $currency->{$fieldKey} ?? null,
            ],
        ];
    }
}
