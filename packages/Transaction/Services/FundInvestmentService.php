<?php

namespace iEXPackages\Transaction\Services;

use App\Models\ContestModel;
use Illuminate\Support\Facades\Log;
use Throwable;

class FundInvestmentService
{
    /**
     * Инвестирование в фонд конкурса.
     *
     * @param float $amount
     */
    public function invest(float $amount): void
    {
        if ($amount <= 0) {
            Log::warning('FundInvestmentService: попытка инвестировать сумму <= 0', ['amount' => $amount]);
            return;
        }

        try {
            $contest = ContestModel::where('status', 1)->first();

            if (!$contest || $contest->is_manual_bank) {
                return;
            }

            $fund = floor($amount * ($contest->percent / 100));
            $newBank = iex_number_format($contest->bank + $fund, 2);

            $contest->update([
                'bank' => $newBank,
                'bank_base' => $newBank,
            ]);
        } catch (Throwable $e) {
            Log::error('Ошибка при инвестировании в фонд: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
