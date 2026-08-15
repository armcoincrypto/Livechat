<?php

namespace App\Http\Controllers\Administrator\Orders;

use App\Http\Controllers\Controller;
use App\Models\OperatorLevelGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LevelGroupController extends Controller
{
    /**
     * Главная страница
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $groups = OperatorLevelGroup::orderByDesc('id')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'count' => $item->operation_level->count()
                ]
            ];
        });

        return response()->json([
            'data' => $groups
        ]);
    }

    /**
     * Обработка и добавление групп
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required'],
            'from_limit' => ['required'],
            'to_limit' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $link = OperatorLevelGroup::create([
            'name' => $request->has('name') ? $request->get('name') : null,
            'from_limit' => $request->has('from_limit') ? $request->get('from_limit') : null,
            'to_limit' => $request->has('to_limit') ? $request->get('to_limit') : null,
            'status' => 1,
        ]);

        $groups = OperatorLevelGroup::all()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'from_limit' => $item->from_limit,
                'to_limit' => $item->to_limit,
            ];
        });

        return response()->json([
            'status' => 0,
            'message' => $link->name . ' успешно добавлен',
            'updated' => $groups
        ]);
    }

    /**
     * Обработка и обновления групп
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $link = OperatorLevelGroup::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => ['required'],
            'from_limit' => ['required'],
            'to_limit' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }


        $link->update([
            'name' => $request->has('name') ? $request->get('name') : null,
            'from_limit' => $request->has('from_limit') ? $request->get('from_limit') : null,
            'to_limit' => $request->has('to_limit') ? $request->get('to_limit') : null,
            'status' => $request->has('status') ? $request->get('status') : 0,
        ]);

        $groups = OperatorLevelGroup::all()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'from_limit' => $item->from_limit,
                'to_limit' => $item->to_limit,
            ];
        });

        return response()->json([
            'status' => 0,
            'message' => $link->name . ' успешно обновлен',
            'updated' => $groups
        ]);
    }

    /**
     * Удаление группы
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy(int $id)
    {
        $item = OperatorLevelGroup::findOrFail($id);
        $oldItem = $item;
        if ($item->operation_level->count() > 0) {
            return response()->json([
                'status' => 1,
                'message' => 'Выбранную группу удалить невозможно, К нему привязаны лимиты'
            ]);
        }

        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => "Группа {$oldItem->name} успешно удалена"
        ]);
    }
}
