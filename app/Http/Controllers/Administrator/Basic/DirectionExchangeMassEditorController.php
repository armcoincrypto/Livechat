<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Basic\DirectionExchangeMassEditorResources;
use App\Models\Currency;
use App\Models\DirectionExchange;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class DirectionExchangeMassEditorController extends Controller
{
    /**
     * Массивное редактирование направление обмена
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $directionExchange = DirectionExchange::with([
            'currency2',
            'currency1',
            'currency2.payment',
            'currency1.payment',
            'currency2.code_currency',
            'currency1.code_currency'])
            ->filter($request->all())
            ->paginate(20);

        return response()->json([
            'items' => new DirectionExchangeMassEditorResources($directionExchange)
        ]);
    }

    /**
     * Обновляем данные
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'details' => ['required'],
            'template' => ['required'],
        ]);

        // Перед добавлением новой валюты проверяем на ошибки
        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        if (in_array($request->template, ['amounts', 'oth-fee', 'pay-fee', 'profit', 'partner-profit']))
        {
            foreach ($request->details as $key_field => $values) {
                foreach ($values as $key => $value) {
                    $directionExchange = DirectionExchange::find($key);
                    $directionExchange->update([
                        $key_field => (float)$value
                    ]);
                }
            }
        } else {
            foreach ($request->details as $key => $value)
            {
                $directionExchange = DirectionExchange::find($key);
                $directionExchange->update([
                    $request->template => $value
                ]);
            }
        }


        return response()->json([
            'status' => 0,
            'message' => 'Данные обновлены'
        ]);
    }
}
