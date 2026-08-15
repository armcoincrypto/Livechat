<?php

namespace iEXPackages\Transaction\Services;

use App\Models\Task;
use App\Models\TaskProfit;
use Illuminate\Support\Facades\Log;

class ProfitCalculationService
{
    public function calculateProfit(?Task $task, float $amountIn)
    {
        // Записываем прибыль от сделки в базу
        $direction_profit_percent = $task->direction_exchange->profit;
        $direction_profit_currency = $task->direction_exchange->profit_s;

        if ($direction_profit_currency > 0 or $direction_profit_percent > 0) {
            $inNumberFormat = (int) $task->direction_exchange->currency1->number_format;
            if ((int) $inNumberFormat > (int) iEXSetting('max_number_format_reserve', 10)) {
                $inNumberFormat = (int) iEXSetting('max_number_format_reserve', 10);
            }

            $direction_amount = iex_number_format(($amountIn * $direction_profit_percent / 100) + $direction_profit_currency, $inNumberFormat);

            try {
                TaskProfit::updateOrCreate([
                    'id_task' => $task->id,
                ], [
                    'id_task' => $task->id,
                    'profit_percent' => $direction_profit_percent,
                    'profit_currency' => $direction_profit_currency,
                    'profit_usd' => calculator_converter($task->direction_exchange->currency1->code_currency->name,  'USD', $direction_amount)
                ]);
            }catch (\Exception $exception) {
                Log::error('TaskProfit: '.$exception->getMessage());
            }
        }
    }
}
