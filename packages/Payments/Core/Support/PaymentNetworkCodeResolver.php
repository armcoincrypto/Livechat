<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Support;

use App\Models\GatewayPayment;
use App\Models\Task;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Определение network_code для ВЫПЛАТ (outgoing).
 *
 * Логика (ключи/связи оставлены как у тебя):
 *  1) ext_options['code_currency'] в GatewayPayment
 *  2) direction_exchange->network_code_out
 *  3) pivot currency_payments.network_code для текущего GatewayPayment (если активные payments)
 *  4) currency2->network_code_out
 *  5) fallback: currency2->code_currency->name (если default_currency=true)
 */
final class PaymentNetworkCodeResolver
{
    public function resolve(
        GatewayPayment $payment,
        Task $task,
        bool $defaultCurrency = true
    ): string {
        // 1) Код валюты из ext_options (модуль "Авто-выплаты")
        $extOptions = (array) ($payment->ext_options ?? []);
        $payCode = (string) ($extOptions['code_currency'] ?? '');

        if ($payCode !== '') {
            return $payCode;
        }

        $direction = $task->direction_exchange ?? null;

        // 2) Проверяем в направлениях (network_code_out)
        if ($direction && !empty($direction->network_code_out)) {
            return (string) $direction->network_code_out;
        }

        if (!$direction || !$direction->currency2) {
            return $defaultCurrency ? (string) ($this->fallbackCurrencyName($task) ?? '') : '';
        }

        // 3) Проверяем currency-level bindings и pivot override
        $currency = $direction->currency2;

        $currencyHasPayments = $currency->relationLoaded('gateway_payments')
            ? $currency->gateway_payments->where('status', '=', 1)->isNotEmpty()
            : $currency->gateway_payments()->where('status', 1)->exists();

        if ($currencyHasPayments) {
            // 3.1) Pivot override для текущего payment
            $gatewayPaymentId = $payment->id ?? null;

            if ($gatewayPaymentId) {
                $pivotNetwork = (string) $currency
                    ->gateway_payments()
                    ->where('currency_payments.gateway_payment_id', $gatewayPaymentId)
                    ->value('currency_payments.network_code');

                if ($pivotNetwork !== '') {
                    return $pivotNetwork;
                }
            }

            // 3.2) Код на уровне валюты (out)
            $currencyCode = (string) ($currency->network_code_out ?? '');
            if ($currencyCode !== '') {
                return $currencyCode;
            }
        }

        // 5) fallback: currency2->code_currency->name
        return $defaultCurrency ? (string) ($currency->code_currency->name ?? '') : '';
    }

    /**
     * Если нужно сохранять в meta заявки (НЕ перезаписывая существующее).
     */
    public function resolveAndStore(
        GatewayPayment $payment,
        Task $task,
        bool $defaultCurrency = true,
        string $metaKey = 'payment_network_code'
    ): string {
        $code = $this->resolve($payment, $task, $defaultCurrency);

        try {
            $meta = $task->meta;

            if ($meta) {
                $existing = (string) ($meta->{$metaKey} ?? '');

                // если уже есть — НЕ перезаписываем, возвращаем существующее
                if ($existing !== '') {
                    return $existing;
                }

                $meta->update([$metaKey => $code]);
            } else {
                // если meta() есть как relation:
                if (method_exists($task, 'meta')) {
                    $task->meta()->create([$metaKey => $code]);
                }
            }
        } catch (Throwable $e) {
            Log::warning('Не удалось сохранить payment network code в meta', [
                'task_id'    => $task->id ?? null,
                'payment_id' => $payment->id ?? null,
                'meta_key'   => $metaKey,
                'code'       => $code,
                'error'      => $e->getMessage(),
            ]);
        }

        return $code;
    }

    protected function fallbackCurrencyName(Task $task): ?string
    {
        $direction = $task->direction_exchange ?? null;
        return $direction?->currency2?->code_currency?->name;
    }
}
