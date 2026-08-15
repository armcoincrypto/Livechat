<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Models\DirectionExchange;
use App\Models\GroupCommission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DirectionExchangeCommissionController extends Controller
{
    /**
     * Получение списка всех групповых комиссий и направлений, связанных с ними.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Загрузка списка направлений обмена (оптимизировано)
        if ($request->has('is_loading_direction')) {
            return response()->json([
                'items' => DirectionExchange::select('id', 'tech_name')
                    ->lazy()
                    ->map(fn ($item) => ['id' => $item->id, 'value' => $item->tech_name])
            ]);
        }

        $groups = GroupCommission::with('directions:id,tech_name')->get()
            ->map(fn($group) => [
                'id' => $group->id,
                'attributes' => [
                    'name' => $group->name,
                    'receiving' => $group->receiving,
                    'direction_exchanges' => $group->directions->map(fn($direction) => [
                        'id' => $direction->id,
                        'name' => $direction->tech_name
                    ]),
                    'created_at' => $group->created_at->translatedFormat('d M Y H:i'),
                    'created_at_human' => $group->created_at->diffForHumans(),
                    'updated_at' => $group->updated_at->translatedFormat('d M Y H:i'),
                    'updated_at_human' => $group->updated_at->diffForHumans(),
                ]
            ]);

        return response()->json([
            'data' => $groups,
            'total' => $groups->count()
        ]);
    }

    /**
     * Создание новой групповой комиссии.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string'],
            'receiving' => ['required', 'string', 'regex:/^[-+*\/]?\d+(\.\d+)?%?$/'],
        ]);


        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $group = GroupCommission::create($request->only(['name', 'receiving']));

        return response()->json([
                'status' => 0,
                'message' => "{$group->name} успешно добавлен"
            ]
        );
    }

    /**
     * Получение данных для редактирования групповой комиссии.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function edit(int $id): JsonResponse
    {
        $group = GroupCommission::with('directions:id')->findOrFail($id);

        return response()->json([
            'id' => $group->id,
            'attributes' => [
                'name' => $group->name,
                'receiving' => $group->receiving,
                'ids_directions' => $group->directions->pluck('id'),
            ]
        ]);
    }

    /**
     * Обновление групповой комиссии и её связей с направлениями.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $group = GroupCommission::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string'],
            'receiving' => ['required', 'string', 'regex:/^[-+*\/]?\d+(\.\d+)?%?$/'],
            'ids_directions' => ['nullable', 'array'],
            'ids_directions.*' => ['exists:direction_exchange,id']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $group->update($request->only(['name', 'receiving']));

        // Обновляем связи с исключёнными направлениями
        $group->directions()->sync($request->ids_directions ?? []);

        return response()->json([
            'status' => 0,
            'message' => $group->name . ' успешно обновлен'
        ]);
    }

    /**
     * Удаление групповой комиссии при отсутствии привязанных направлений.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $group = GroupCommission::withCount('directions')->findOrFail($id);

        if ($group->directions_count > 0) {
            return response()->json([
                'status' => 1,
                'message' => 'Эту группу нельзя удалить, так как к ней привязаны направления.'
            ]);
        }

        $groupName = $group->name;
        $group->delete();

        return response()->json(['status' => 0, 'message' => "{$groupName} успешно удален"]);
    }
}
