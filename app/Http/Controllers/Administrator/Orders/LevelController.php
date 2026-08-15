<?php

namespace App\Http\Controllers\Administrator\Orders;

use App\Http\Controllers\Controller;
use App\Models\OperationLevel;
use App\Models\OperatorLevelGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class LevelController extends Controller
{
    /**
     * Главная страница
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $lists = OperationLevel::orderByDesc('id')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'group' => [
                        'id' => $item->id_level_group,
                        'name' => $item->level_group?->name
                    ],

                    'manager' => [
                        'id' => $item->id_operator,
                        'name' => $item->manager?->name
                    ],

                    'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                    'created_at_human' => $item->created_at->diffForHumans(),
                    'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                    'updated_at_human' => $item->updated_at->diffForHumans(),
                ]
            ];
        });

        $groups = OperatorLevelGroup::all()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'from_limit' => $item->from_limit,
                'to_limit' => $item->to_limit,
            ];
        });


        $users = User::role(Role::all())->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name. '('.$item->email.')'
            ];
        });

        return response()->json([
            'data' => $lists,
            'total' => count($lists),
            'groups' => $groups,
            'users' => $users
        ]);
    }

    /**
     * Обработка и добавление записи
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_level_group' => ['required'],
            'id_operator' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        OperationLevel::create([
            'id_level_group' => $request->has('id_level_group') ? $request->get('id_level_group') : 0,
            'id_operator' => $request->has('id_operator') ? $request->get('id_operator') : 0,
            'status' => $request->has('status') ? $request->get('status') : 0,
        ]);


        return response()->json([
            'status' => 0,
            'message' => 'Лимит успешно добавлен',
        ]);
    }

    /**
     * Форма редактирования записи
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id)
    {
        $item = OperationLevel::findOrFail($id);
        $groups = OperatorLevelGroup::all()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'from_limit' => $item->from_limit,
                'to_limit' => $item->to_limit,
            ];
        });


        $users = User::role(Role::all())->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name. '('.$item->email.')'
            ];
        });

        return response()->json([
            'attributes' => [
                'id_group_level' => $item->id_level_group,
                'id_operator' => $item->id_operator,
                'status' => (bool)$item->status,
            ],
            'groups' => $groups,
            'users' => $users,
        ]);
    }

    /**
     * Обработка и обновления записи
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $link = OperationLevel::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'id_level_group' => ['required'],
            'id_operator' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }


        $link->update([
            'id_level_group' => $request->has('id_level_group') ? $request->get('id_level_group') : 0,
            'id_operator' => $request->has('id_operator') ? $request->get('id_operator') : 0,
            'status' => $request->has('status') ? $request->get('status') : 0,
        ]);

        return response()->json([
            'status' => 0,
            'message' => 'Лимит успешно обновлен',
        ]);
    }

    /**
     * Удаление записи
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy(int $id)
    {
        $link = OperationLevel::findOrFail($id);
        $link->delete();

        return response()->json([
            'status' => 0,
            'message' => 'Успешно удален'
        ]);
    }
}
