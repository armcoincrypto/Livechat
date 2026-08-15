<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Models\FileParserGroup;
use App\Models\ReserveFileGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReserveFileGroupController extends Controller
{
    /**
     * Главная страница
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $links = ReserveFileGroup::orderByDesc('id')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'status' => (bool)$item->status,
                    'link' => $item->link,
                    'count' => $item->reserves_files->count()
                ]
            ];
        });

        return response()->json([
            'data' => $links
        ]);
    }

    /**
     * Обработка и добавление
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'min:2'],
            'link' => ['required', 'min:3'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $link = ReserveFileGroup::create([
            'name' => $request->has('name') ? $request->get('name') : null,
            'link' => $request->has('link') ? $request->get('link') : null,
            'status' => $request->has('status') ? $request->get('status') : 0,
        ]);

        $categories = ReserveFileGroup::orderByDesc('id')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'link' => $item->link
            ];
        });

        return response()->json([
            'status' => 0,
            'message' => $link->name . ' успешно добавлен',
            'updated' => $categories
        ]);
    }

    /**
     * Форма редактирования
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit(int $id)
    {
        $item = ReserveFileGroup::findOrFail($id);

        return view('admin.basic.reserves.files.groups.edit', compact('item'));
    }

    /**
     * Обработка и обновления
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $link = ReserveFileGroup::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'min:2'],
            'link' => ['required', 'min:3'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }


        $link->update([
            'name' => $request->has('name') ? $request->get('name') : null,
            'link' => $request->has('link') ? $request->get('link') : null,
            'status' => $request->has('status') ? $request->get('status') : 0,
        ]);

        $categories = ReserveFileGroup::orderByDesc('id')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'link' => $item->link
            ];
        });

        return response()->json([
            'status' => 0,
            'message' => "Категория {$link->name} успешно обновлена",
            'updated' => $categories
        ]);
    }

    /**
     * Удаление ссылок
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy(int $id)
    {
        $link = ReserveFileGroup::findOrFail($id);
        $oldItem = $link;
        if ($link->reserves_files->count() > 0)
        {
            return response()->json([
                'status' => 1,
                'message' => 'Выбранный файл удалить невозможно, К нему привязаны резервы'
            ]);
        }

        $link->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name .' успешно удален'
        ]);
    }
}
