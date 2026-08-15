<?php

namespace iEXPackages\Transaction\Services;

use App\Models\CurrencyAnalytics;
use App\Models\Task;
use Illuminate\Support\Facades\Log;
use Throwable;
use Brick\Math\BigDecimal;

class CurrencyAnalyticsService
{
    /**
     * Обновляет агрегированную статистику по валютам на основе завершённой задачи.
     *
     * @param Task  $task      Задача, по которой фиксируем статистику.
     * @param float $amountIn  Сумма в валюте "Отдаю" (currency1) в её номинале.
     * @param float $amountOut Сумма в валюте "Получаю" (currency2) в её номинале.
     */
    public function updateStats(Task $task, float $amountIn, float $amountOut): void
    {
        if (! $task->relationLoaded('direction_exchange') || ! $task->direction_exchange) {
            $task->load([
                'direction_exchange.currency1.currency_analytics',
                'direction_exchange.currency2.currency_analytics',
                'direction_exchange.currency1.code_currency',
                'direction_exchange.currency2.code_currency',
            ]);
        }

        if (! $task->direction_exchange) {
            Log::warning('Пропущено обновление статистики валют: отсутствует направление обмена у задачи.', [
                'task_id' => $task->id,
            ]);
            return;
        }

        try {
            // Обновляем статистику по валюте "Отдаю" (currency1)
            if ($amountIn !== 0.0) {
                $this->updateInCurrencyStats($task, $amountIn);
            }
            // Обновляем статистику по валюте "Получаю" (currency2)
            if ($amountOut !== 0.0) {
                $this->updateOutCurrencyStats($task, $amountOut);
            }
        } catch (Throwable $e) {
            Log::error('Ошибка обновления статистики валют.', [
                'task_id'   => $task->id,
                'message'   => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }

    /**
     * Обновление статистики по валюте "Отдаю" (currency1).
     */
    private function updateInCurrencyStats(Task $task, float $amountIn): void
    {
        $direction = $task->direction_exchange;

        if (! $direction || ! $direction->currency1) {
            Log::warning('Пропущено обновление статистики in: не найдена валюта currency1.', [
                'task_id' => $task->id,
            ]);
            return;
        }

        $currency = $direction->currency1;

        if (! $currency->code_currency) {
            Log::warning('Пропущено обновление статистики in: у валюты отсутствует связанный код валюты.', [
                'task_id'     => $task->id,
                'currency_id' => $currency->id,
            ]);
            return;
        }

        $analytics = $currency->currency_analytics;

        // Количество завершённых заявок по этой валюте в роли "Отдаю" (currency1)
        $orderCount = Task::query()
            ->leftJoin('direction_exchange', 'direction_exchange.id', '=', 'tasks.id_direction_exchange')
            ->where('direction_exchange.id_currency1', $currency->id)
            ->where('tasks.status', 4)
            ->count();

        // Накопление суммы в номинале валюты (in_amount)
        $currentAmount = BigDecimal::of((string) ($analytics?->in_amount ?? '0'));
        $deltaAmount   = BigDecimal::of((string) $amountIn);
        $newAmount     = $currentAmount->plus($deltaAmount);

        $newExchangeCount = (int) ($analytics?->in_count_exchange ?? 0) + 1;

        // Строковое значение для сохранения (при необходимости можно ограничить scale)
        $newAmountString = (string) $newAmount; // или $newAmount->toScale(8);

        // Накопление суммы в USD по дельте, а не пересчёт всей суммы
        $currentAmountUsd = BigDecimal::of((string) ($analytics?->in_amount_usd ?? '0'));
        $deltaAmountUsdRaw = calculator_converter(
            $currency->code_currency->name,
            'USD',
            (string) $amountIn
        );
        $deltaAmountUsd = BigDecimal::of((string) $deltaAmountUsdRaw);
        $newAmountUsd   = $currentAmountUsd->plus($deltaAmountUsd);
        $newAmountUsdString = (string) $newAmountUsd;

        CurrencyAnalytics::updateOrCreate(
            [
                'id_currency' => $currency->id,
            ],
            [
                'in_amount'          => $newAmountString,
                'in_amount_usd'      => $newAmountUsdString,
                'in_count_exchange'  => $newExchangeCount,
                'in_order_count'     => $orderCount,
            ]
        );
    }

    /**
     * Обновление статистики по валюте "Получаю" (currency2).
     */
    private function updateOutCurrencyStats(Task $task, float $amountOut): void
    {
        $direction = $task->direction_exchange;

        if (! $direction || ! $direction->currency2) {
            Log::warning('Пропущено обновление статистики out: не найдена валюта currency2.', [
                'task_id' => $task->id,
            ]);
            return;
        }

        $currency = $direction->currency2;

        if (! $currency->code_currency) {
            Log::warning('Пропущено обновление статистики out: у валюты отсутствует связанный код валюты.', [
                'task_id'     => $task->id,
                'currency_id' => $currency->id,
            ]);
            return;
        }

        $analytics = $currency->currency_analytics;

        // Количество завершённых заявок по этой валюте в роли "Получаю" (currency2)
        $orderCount = Task::query()
            ->leftJoin('direction_exchange', 'direction_exchange.id', '=', 'tasks.id_direction_exchange')
            ->where('direction_exchange.id_currency2', $currency->id)
            ->where('tasks.status', 4)
            ->count();

        // Накопление суммы в номинале валюты (out_amount)
        $currentAmount = BigDecimal::of((string) ($analytics?->out_amount ?? '0'));
        $deltaAmount   = BigDecimal::of((string) $amountOut);
        $newAmount     = $currentAmount->plus($deltaAmount);

        $newExchangeCount = (int) ($analytics?->out_count_exchange ?? 0) + 1;

        $newAmountString = (string) $newAmount; // или $newAmount->toScale(8);

        // Накопление суммы в USD по дельте
        $currentAmountUsd = BigDecimal::of((string) ($analytics?->out_amount_usd ?? '0'));

        $deltaAmountUsdRaw = calculator_converter(
            $currency->code_currency->name,
            'USD',
            (string) $amountOut
        );
        $deltaAmountUsd = BigDecimal::of((string) $deltaAmountUsdRaw);
        $newAmountUsd   = $currentAmountUsd->plus($deltaAmountUsd);
        $newAmountUsdString = (string) $newAmountUsd;

        CurrencyAnalytics::updateOrCreate(
            [
                'id_currency' => $currency->id,
            ],
            [
                'out_amount'          => $newAmountString,
                'out_amount_usd'      => $newAmountUsdString,
                'out_count_exchange'  => $newExchangeCount,
                'out_order_count'     => $orderCount,
            ]
        );
    }
}
