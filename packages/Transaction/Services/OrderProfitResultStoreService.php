<?php

namespace iEXPackages\Transaction\Services;

use App\Models\OrderProfitResult;
use App\Models\Task;
use iEXPackages\Transaction\DTO\OrderProfitResultDto;
use Illuminate\Support\Facades\Log;

class OrderProfitResultStoreService
{
    public function storeForTask(Task $task, OrderProfitResultDto $result): ?OrderProfitResult
    {
        try {
            return OrderProfitResult::updateOrCreate(
                ['task_id' => $task->id],
                [
                    'task_id'                 => $task->id,
                    'profit_amount'           => $result->profitAmount,
                    'profit_currency_code'    => $result->profitCurrencyCode,
                    'profit_amount_usd'       => $result->profitAmountUsd,
                    'base_currency_code'      => $result->baseCurrencyCode,
                    'profit_percent_effective'=> $result->effectivePercent,
                    'rates_snapshot'          => $result->ratesSnapshot,
                    'breakdown_json'          => [
                        'components' => $result->components,
                    ],
                    'calculated_at'           => now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('OrderProfitResultStoreService: failed to store profit result', [
                'task_id' => $task->id ?? null,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }
}
