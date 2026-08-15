<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Support;

use App\Models\GatewayMerchant;
use App\Models\Task;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Сервис определения network_code для пары (GatewayMerchant, Task).
 *
 * Логика:
 *  1) ext_options['network_code_currency'] на мерчанта;
 *  2) direction_exchange->network_code при наличии активных мерчантов;
 *  3) currency1->merchants pivot (gateway_merchant_id + network_code);
 *  4) currency1->network_code;
 *  5) фолбэк — значение, которое ты передал как $fallbackCurrency (обычно getCurrency()).
 */
final class NetworkCodeResolver
{
    /**
     * @param GatewayMerchant $merchant
     * @param Task $task
     * @param string|null $fallbackCurrency Строковый фолбэк, который выбирается снаружи (например, $request->getCurrency()).
     * @return string
     */
    public function resolve(
        GatewayMerchant $merchant,
        Task $task,
        ?string $fallbackCurrency = null,
    ): string {
        // 1) Явный код в настройках мерчанта
        $explicit = (string) (($merchant->ext_options['network_code_currency'] ?? '') ?: '');

        if ($explicit !== '') {
            return $explicit;
        }

        $direction = $task->direction_exchange ?? null;
        $currency  = $direction?->currency1 ?? null;

        // 2) Код на уровне направления (только при наличии активных мерчантов)
        if ($direction) {
            $directionCode = (string) ($direction->network_code ?? '');
            if ($directionCode !== '') {
                $hasActiveMerchants = $direction->relationLoaded('merchants')
                    ? $direction->merchants->where('status', 1)->isNotEmpty()
                    : $direction->merchants()->where('status', 1)->exists();

                if ($hasActiveMerchants) {
                    return $directionCode;
                }
            }
        }

        // 3) Переопределение на уровне валюты через pivot для текущего мерчанта
        $merchantId = (int) ($merchant->id ?? 0);
        if ($currency && $merchantId > 0) {
            if ($currency->relationLoaded('merchants')) {
                $related = $currency->merchants
                    ->first(static fn ($m) => (int) ($m->pivot->gateway_merchant_id ?? 0) === $merchantId);

                $pivotCode = (string) ($related?->pivot?->network_code ?? '');
                if ($pivotCode !== '') {
                    return $pivotCode;
                }
            } else {
                $pivotCode = (string) $currency->merchants()
                    ->where('currency_merchants.gateway_merchant_id', $merchantId)
                    ->value('network_code');

                if ($pivotCode !== '') {
                    return $pivotCode;
                }
            }
        }

        // 4) Код на самой валюте
        if ($currency) {
            $currencyCode = (string) ($currency->network_code ?? '');
            if ($currencyCode !== '') {
                return $currencyCode;
            }
        }

        // 5) Фолбэк — тот, что пришёл "снаружи"
        return $fallbackCurrency ?? '';
    }

    /**
     * То же самое, но сразу записывает network_code в $task->meta.
     */
    public function resolveAndStore(
        GatewayMerchant $merchant,
        Task $task,
        ?string $fallbackCurrency = null
    ): string {
        $code = $this->resolve($merchant, $task, $fallbackCurrency);

        try {
            $meta = $task->meta;

//            if (!$meta) {
//                // если мета отсутствует — просто пишем
//                $task->meta()->create(['merchant_network_code' => $code]);
//                return $code;
//            }
//
//            $existing = $meta->merchant_network_code ?? null;
//
//            // если уже есть значение → НЕ перезаписывать
//            if ($existing !== null && $existing !== '') {
//                return $existing;
//            }

            // если пусто → обновляем
            $meta->update(['merchant_network_code' => $code]);

        } catch (\Throwable $e) {
            Log::warning('Не удалось сохранить merchant_network_code в meta задачи', [
                'task_id' => $task->id ?? null,
                'code'    => $code,
                'error'   => $e->getMessage(),
            ]);
        }

        return $code;
    }

    private function safeMessage(Throwable $e): string
    {
        return $e->getMessage() ?: (get_class($e) . ' without message');
    }
}
