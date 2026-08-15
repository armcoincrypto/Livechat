<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Basic\DirectionRequisiteResources;
use App\Models\DirectionExchange;
use App\Models\DirectionRequisite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DirectionRequisitesController extends Controller
{
    /**
     * Список уведомлений
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $items = DirectionRequisite::orderBy('id')->paginate(20);

        return response()->json([
            'items' => new DirectionRequisiteResources($items)
        ]);
    }

    /**
     * Обработка и добавление уведомлений
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {

        if($request->has('updateField'))
        {
            if($request->get('updateField') == 'status')
            {
                DirectionRequisite::find((int)$request->id)->update([
                    'status' => (int)$request->status
                ]);

                return response()->json([
                    'status' => 0
                ]);
            }
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'account_number' => 'required'
        ]);

        // Перед добавлением новой валюты проверяем на ошибки
        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        // Если все удачно, добавляем
        $requisite = DirectionRequisite::create([
            'name' => security_xss($request->name) ?? '',
            'account_number' => security_xss($request->account_number) ?? ''
        ]);

        return response()->json([
            'status' => 0,
            'message' => $requisite->name . ' успешно добавлен'
        ]);
    }

    /**
     * Форма редактирования уведомлений
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id, Request $request)
    {
        $item = DirectionRequisite::findOrFail($id);

        if($request->has('is_loading_direction'))
        {
            $directionExchange = DirectionExchange::select('id', 'tech_name', 'status')
                ->lazy()->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'value' => $item->tech_name
                    ];
                });

            return response()->json([
                'items' => $directionExchange,
                'values' => $item->direction_exchange->pluck('id')
            ]);
        }


        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'name' => $item->name,
                'account_number' => $item->account_number,
                'status' => (bool)$item->status,
                'limit_views' => $item->limit_views,
                'limit_day' => $item->limit_day,
                'limit_month' => $item->limit_month,
            ]
        ]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $requisite = DirectionRequisite::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'account_number' => 'required',
            'limit_day' => 'required|numeric',
            'limit_month' => 'required|numeric',
            'limit_views' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }


        $options = [
            'account_number' => $request->has('account_number') ? $request->get('account_number') : '',
            'name' => ($request->has('name') ? $request->get('name') : null),
            'limit_day' => ($request->has('limit_day') ? $request->get('limit_day') : 0),
            'limit_month' => ($request->has('limit_month') ? $request->get('limit_month') : 0),
            'status' => ($request->has('status') ? $request->get('status') : 0),
            'limit_views' => ($request->has('limit_views') ? $request->get('limit_views') : 0),
        ];

        $requisite->update($options);
        $requisite->direction_exchange()->sync($request->directions_exchanges ?? []);


        return response()->json([
            'status' => 0,
            'message' => $requisite->name . ' успешно обновлен'
        ]);
    }

    /**
     * Удаление уведомлений
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     *
     */
    public function destroy(int $id)
    {
        $field = DirectionRequisite::findOrFail($id);
        $oldItem = $field;
        $field->direction_exchange()->detach($field->id);
        $field->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name . ' успешно удален'
        ]);
    }
}
