<?php

namespace App\Http\Controllers\Administrator\Vue;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\DirectionExchange;
use Illuminate\Http\Request;

class DirectionExchangeVueController extends Controller
{

    /**
     * Проверка направления обмена
    */
    public function checkValidate(Request $request)
    {
        $currencyIn = (int)$request->currency_in;
        $currencyOut = (int)$request->currency_out;

        // Получаем информацию по валюте
        $currencyData = Currency::find($currencyIn);
        $currencyOutData = Currency::find($currencyOut);


        // Проверяем есть ли эта направление в базе
        $existsPair = DirectionExchange::where([
            ['id_currency1', '=', $currencyIn],
            ['id_currency2', '=', $currencyOut],
        ])->exists();


        // Проверяем, если ли обратная пара
        $reversePair = DirectionExchange::where([
            ['id_currency1', '=', $currencyOut],
            ['id_currency2', '=', $currencyIn],
        ])->exists();



        return response()->json([
            'exists_pair' => (int)$existsPair,
            'reverse_pair' => (int)$reversePair,
            'currency_in_name' => $currencyData->tech_name,
            'currency_out_name' => $currencyOutData->tech_name,
        ]);
    }
}
