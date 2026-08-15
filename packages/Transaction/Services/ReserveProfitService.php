<?php

namespace iEXPackages\Transaction\Services;

use App\Models\Currency;
use App\Models\Reserve;

class ReserveProfitService
{
    public function updateReserveAfterSuccess(Reserve $reserve, float $amountIn, Currency $currencyIn)
    {
        // После успешного выполнения заявки, снимаем % прибыли с валюты "Отдаю" (profit_percent_reserve)
        $profit_percent = (float) $currencyIn->profit_percent_reserve;

        if ($profit_percent > 0) {
            $inNumberFormat = (int) $currencyIn->number_format;
            if ((int) $inNumberFormat > (int) iEXSetting('max_number_format_reserve', 10)) {
                $inNumberFormat = (int) iEXSetting('max_number_format_reserve', 10);
            }

            $amount = iex_number_format($amountIn * $profit_percent / 100, $inNumberFormat);
            $reserve->update([
                'summa' => iex_number_format($reserve->summa - $amount, $inNumberFormat),
            ]);
        }
    }
}
