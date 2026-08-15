<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Models\DirectionExchange;
use App\Models\DirectionExchangeMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DirectionExchangeModeController extends Controller
{
    /**
     * Дополнительные фильтры
     */
    protected array $allowFiltered = [
        'is_enabled_module_direction_mode',
    ];

    /**
     * Список групп направлений для режимов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $modes = DirectionExchangeMode::orderBy('id', 'desc')
            ->withCount('direction_exchange')->get()->map(function ($value) {
            return [
                'id' => $value->id,
                'attributes' => [
                    'name' => $value->name,
                    'status' => (bool)$value->status,
                    'count' => $value->direction_exchange_count
                ]
            ];
        })->values();

        return response()->json([
            'data' => $modes,
            'total' => count($modes)
        ]);
    }

    /**
     * Обработка и добавления нового режима
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if($request->has('updateField'))
        {
            if($request->get('updateField') == 'status')
            {
                if((int)$request->status == 1) {
                    \DB::table('direction_exchange_modes')->update(['status' => 0]);
                    DirectionExchangeMode::find($request->id)->update(['status' => 1]);
                } else {
                    \DB::table('direction_exchange_modes')->update(['status' => 0]);
                }

                return response()->json([
                    'status' => 0
                ]);
            }
        }


        $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $item = DirectionExchangeMode::create([
            'name' => $request->name
        ]);

        return response()->json([
            'status' => 0,
            'message' => $item->name . ' успешно добавлен'
        ]);
    }

    /**
     * Форма изменения режима
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id, Request $request)
    {
        $item = DirectionExchangeMode::findOrFail($id);

        if($request->has('is_loading_direction'))
        {
            $direction = DirectionExchange::select('id', 'status','tech_name')->isEnabled()->get()->map(function($item) {
                return [
                    'id' => $item->id,
                    'value' => $item->tech_name
                ];
            });

            return response()->json([
                'items' => $direction,
                'values' => $item->direction_exchange->pluck('id')
            ]);
        }

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'name' => $item->name
            ]
        ]);
    }

    /**
     * Обработчик обновления режима
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $group = DirectionExchangeMode::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $group->update([
            'name' => ($request->has('name') ? $request->get('name') : null),
        ]);

        $group->direction_exchange()->sync($request->ids_direction_exchange);


        return response()->json([
            'status' => 0,
            'message' => $group->name . ' успешно обновлен'
        ]);
    }

    /**
     * Удалить режим
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy($id)
    {
        $item = DirectionExchangeMode::findOrFail($id);
        $oldItem = $item;
        if (isset($item->direction_exchange)) {
            $item->direction_exchange()->sync([]);
        }
        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name. ' успешно удален'
        ]);
    }
}
