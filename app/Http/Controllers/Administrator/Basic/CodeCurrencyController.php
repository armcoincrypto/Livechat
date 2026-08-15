<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Basic\CurrencyCodesResources;
use App\Models\CodeCurrency;
use App\Models\GroupParserExchange;
use App\Models\ParserExchange;
use App\Models\ParserFormulaRates;
use App\Support\Facades\iEXApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use function Clue\StreamFilter\fun;

class CodeCurrencyController extends Controller
{
    public function index(Request $request)
    {
        if(isset($request->is_unique))
        {
            // Проверяем код в списке созданных
            $validateCode = CodeCurrency::where('name', Str::upper(security_xss($request->code)))->exists();
            if($validateCode) {
                $find = CodeCurrency::where('name', Str::upper(security_xss($request->code)))->first();

                return response()->json([
                    'id_code' => $find->id,
                    'is_exists' => 1
                ]);
            }

            return [];
        }

        $code_currencies = CodeCurrency::with(['currency' => function ($q) {
            $q->select('id', 'tech_name', 'id_payment', 'id_code_currency');
        }])->active()
            ->filter($request->all());

        if(!$request->has('sorting_order')) {
            $code_currencies = $code_currencies->orderBy('id', 'desc');
        }

        $code_currencies = $code_currencies->paginate(iEXSetting('admin_code_pagination', 20));

        $admin_hidden_columns = explode(',', iEXSetting('admin_codes_hidden_columns'));
        $allowedColumns = ['name', 'currencies', 'exchange_rate'];

        return response()->json([
            'items' => new CurrencyCodesResources($code_currencies),
            'selected_columns' => collect($admin_hidden_columns)->map(function ($item) {
                return $item;
            })->reject(fn($item) => !in_array($item, $allowedColumns))->values(),
            'per_page' => (int)iEXSetting('admin_code_pagination', 20),
            'partnerCode' => config('partners-bonus.name') ?? ''
        ]);
    }

    /**
     * Обработать и добавить код валюты
     *
     * @throws \Exception
     */
    public function store(Request $request): JsonResponse
    {
        // Валидация входных данных
        $validator = Validator::make($request->all(), [
            'code' => 'required|string'
        ]);

        if ($validator->fails())
        {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        // Разделение кода по запятой и очистка пробелов
        $codes = array_map('trim', explode(',', $request->get('code')));
        $added = [];
        foreach ($codes as $code) {
            $codeSanitized = Str::upper(remove_all_spaces(security_xss($code)));
            if (!$codeSanitized) {
                continue;
            }

            $exists = CodeCurrency::where('name', $codeSanitized)->exists();
            if ($exists) {
                continue;
            }
            $item = CodeCurrency::create([
                'name' => $codeSanitized,
                'sign' => $codeSanitized,
                'add_to_course' => 0,
                'id_parser_exchange' => 0,
                'add_to_course_formula' => 0,
                'id_parser_formula' => 0,
            ]);
            $added[] = $item->name;
        }

        if (empty($added)) {
            return response()->json([
                'status' => 1,
                'message' => 'Все введённые коды уже существуют.'
            ]);
        }

        return response()->json([
            'status' => 0,
            'message' => 'Успешно добавлены коды: ' . implode(', ', $added)
        ]);
    }

    /**
     * Форма редактирования кода валюты
     *
     * @return JsonResponse
     */
    public function edit(int $id)
    {
        $item = CodeCurrency::findOrFail($id);


        return response()->json([
            'partnerCode' => config('partners-bonus.name') ?? '',
            'item' => $item
        ]);
    }

    /**
     * Обработать и обновить код валюты
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required',
        ]);

        // Перед добавлением новой валюты проверяем на ошибки
        if ($validator->fails())
        {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $currency_code = CodeCurrency::findOrFail($id);


        $get_code_name = Str::upper(remove_all_spaces(security_xss($request->get('code'))));

        if($currency_code->name !== $get_code_name) {
            $validateCode = CodeCurrency::where('name', $get_code_name)->exists();
            if($validateCode) {
                return response()->json([
                    'status' => 1,
                    'message' => __('Этот код уже существует, добавьте другой')
                ]);
            }
        }

        $currency_code->update([
            'name' => $get_code_name,
            'sign' => $get_code_name,
            'add_to_course' => (float)$request->input('add_to_course', 0),
            'id_parser_exchange' => (int)$request->input('id_parser_exchange', 0),
            'add_to_course_formula' => (float)$request->input('add_to_course_formula', 0),
            'id_parser_formula' => (int)$request->input('id_parser_formula', 0),
            'internal_rate' => (float)$request->input('internal_rate', 0),
        ]);

        return response()->json([
            'status' => 0,
            'message' => 'Код валюты успешно обновлен',
        ]);
    }


    public function destroy(int $id)
    {
        $code_currency = CodeCurrency::find($id);

        if (!$code_currency) {
            return response()->json([
                'status' => 1,
                'message' => 'Код валюты не найден'
            ], 404);
        }

        $oldItem = $code_currency;
        if (isset($code_currency->currency) and $code_currency->currency->count() > 0)
        {
            return response()->json([
                'status' => 1,
                'message' => $code_currency->name.' не удален, найдены привязанные валюты'
            ]);
        }

        $code_currency->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name.' успешно удален'
        ]);
    }
}
