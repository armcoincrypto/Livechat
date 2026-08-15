<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Models\ReserveFile;
use App\Models\ReserveFileGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReservesFilesController extends Controller
{
    /**
     * Главная страница
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $files = ReserveFile::orderByDesc('id')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'amount' => $item->amount,
                    'number_format' => $item->number_format,
                    'status' => (bool)$item->status,
                    'file_name' => $item->file_group?->name,
                    'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                    'created_at_human' => $item->created_at->diffForHumans(),
                    'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                    'updated_at_human' => $item->updated_at->diffForHumans(),
                ]
            ];
        });

        $categories = ReserveFileGroup::orderBy('id', 'desc')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'link' => $item->link,
                'status' => (bool)$item->status
            ];
        });


        return response()->json([
            'data' => $files,
            'total' => count($files),
            'categories' => $categories
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
            'name' => ['required'],
            'id_group' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $rate = ReserveFile::create([
            'name' => $request->has('name') ? $request->get('name') : null,
            'id_group' => $request->has('id_group') ? $request->get('id_group') : 0,
            'status' => $request->has('status') ? $request->get('status') : 0,
            'number_format' => $request->has('number_format') ? $request->get('number_format') : 0,
        ]);

        return response()->json([
            'status' => 0,
            'message' => "{$rate->name} успешно добавлен"
        ]);
    }

    /**
     * Форма редактирования
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id)
    {
        $item = ReserveFile::findOrFail($id);

        $categories = ReserveFileGroup::orderBy('id', 'desc')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'link' => $item->link,
                'status' => (bool)$item->status
            ];
        });

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'name' => $item->name,
                'amount' => $item->amount,
                'number_format' => $item->number_format,
                'status' => (bool)$item->status,
                'id_group' => $item->id_group,
                'file_name' => $item->file_group?->name,
                'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                'created_at_human' => $item->created_at->diffForHumans(),
                'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                'updated_at_human' => $item->updated_at->diffForHumans(),
            ],
            'categories' => $categories
        ]);
    }

    /**
     * Обработка и обновление
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $reserve = ReserveFile::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'name' => ['required'],
            'id_group' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $reserve->update([
            'name' => $request->has('name') ? $request->get('name') : null,
            'id_group' => $request->has('id_group') ? $request->get('id_group') : 0,
            'status' => $request->has('status') ? $request->get('status') : 0,
            'number_format' => $request->has('number_format') ? $request->get('number_format') : 0,
        ]);

        return response()->json([
            'status' => 0,
            'message' => "{$reserve->name} успешно обновлен"
        ]);
    }

    /**
     * Удаление
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy(int $id)
    {
        $reserve = ReserveFile::findOrFail($id);
        $oldItem = $reserve;
        if ($reserve->reserves->count() > 0)
        {
            return response()->json([
                'status' => 1,
                'message' => 'Выбранный резерв удалить невозможно, К нему привязаны валюты'
            ]);
        }

        $reserve->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name .' успешно удален'
        ]);
    }
}
