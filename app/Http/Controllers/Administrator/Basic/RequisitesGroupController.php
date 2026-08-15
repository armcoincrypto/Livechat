<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Models\RequisitesGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RequisitesGroupController extends Controller
{
    /**
     * Список групп платежных реквизитов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $groups = RequisitesGroup::orderBy('sorting')->get()->map(function($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'status' => (bool)$item->status,
                    'sorting' => $item->sorting
                ]
            ];
        });

        return response()->json([
            'data' => $groups
        ]);
    }

    /**
     * Форма добавления группы
     */
    public function create()
    {
        return view('admin.basic.requisites.groups.create');
    }

    /**
     * Обработка и добавления новой группы
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'status' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }
        $item = RequisitesGroup::create([
            'name' => $request->name,
            'status' => ($request->has('status') ? $request->get('status') : 0),
        ]);

        $item->sorting = $item->id;
        $item->save();

        return response()->json([
            'status' => 0,
            'message' => $item->name .' успешно добавлен'
        ]);
    }

    /**
     * Форма изменения группы
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit(int $id)
    {
        $item = RequisitesGroup::findOrFail($id);

        return view('admin.basic.requisites.groups.edit', compact('item'));
    }

    /**
     * Обработчик обновления группы
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $group = RequisitesGroup::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'status' => 'required',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $group->update([
            'name' => ($request->has('name') ? $request->get('name') : null),
            'status' => ($request->has('status') ? $request->get('status') : 0),
        ]);

        return response()->json([
            'status' => 0,
            'message' => $group->name .' успешно обновлен'
        ]);
    }

    /**
     * Удалить группу
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy($id)
    {
        $item = RequisitesGroup::findOrFail($id);
        $oldItem = $item;
        if ($item->requisites_all->count() > 0)
        {
            return response()->json([
                'status' => 1,
                'message' => 'Удаление невозможно, к группе привязаны реквизиты'
            ]);
        }

        $item->delete();
        return response()->json([
            'status' => 0,
            'message' => "{$oldItem->name} успешно удален"
        ]);
    }
}
