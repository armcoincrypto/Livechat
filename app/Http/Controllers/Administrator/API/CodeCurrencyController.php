<?php

namespace App\Http\Controllers\Administrator\API;

use App\Http\Controllers\Controller;
use App\Models\CodeCurrency;
use App\Models\GroupParserExchange;
use App\Models\ParserExchange;
use App\Models\ParserFormulaRates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CodeCurrencyController extends Controller
{
    /**
     * Список кодов валют
     *
     * @param Request $request
     * @return array
     */
    public function index(Request $request): array
    {
        $code_currencies = CodeCurrency::with(['parser_exchange', 'currency' => function ($q) {
            $q->select('id', 'id_code_currency', 'id_payment');
        }, 'currency.payment' => function ($q) {
            $q->select('id', 'name');
        }, 'currency.code_currency' => function ($q) {
            $q->select('id', 'name');
        }])->active()
            ->filter($request->all());

        if(!$request->has('sorting_order')) {
            $code_currencies = $code_currencies->orderBy('id', 'desc');
        }

        $code_currencies = $code_currencies->paginate(iEXSetting('admin_code_pagination', 20));

        // Парсинг из остальных источников
        $group_rates = GroupParserExchange::select('id', 'name', 'status')->where('status', '=', 1)->get();
        $parser_exchange_allowed = ParserExchange::select('id', 'summa', 'name', 'value', 'id_group', 'status')->where('status', '=', 1)->cursor();

        $parser_exchange = [];
        foreach ($parser_exchange_allowed as $parser_value) {
            $parser_exchange[$parser_value->id_group][] = [
                'id' => $parser_value->id,
                'summa' => $parser_value->summa,
                'name' => $parser_value->name,
                'value' => $parser_value->value,
            ];
        }


        // Получение курсов по формуле
        $rates_formula = ParserFormulaRates::select('id', 'summa', 'title', 'status')->where('status', '=', 1)->get();

        return [
            'codes' => $code_currencies,
            'group_rates' => $group_rates,
            'parser_exchange' => $parser_exchange,
            'rates_formula' => $rates_formula,
            'filter' => $request->all()
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required'
        ]);

        // Перед добавлением новой валюты проверяем на ошибки
        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        } else {
            $item = CodeCurrency::create([
                'name' => $request->get('code'),
                'add_to_course' => $request->has('add_to_course') ? $request->get('add_to_course') : 0,
                'id_parser_exchange' => $request->has('id_parser_exchange') ? $request->get('id_parser_exchange') : 0,
                'add_to_course_formula' => $request->has('add_to_course_formula') ? $request->get('add_to_course_formula') : 0,
                'id_parser_formula' => $request->has('id_parser_formula') ? $request->get('id_parser_formula') : 0,
            ]);
        }

        return response()->json([
            'status' => 0,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
