<?php

namespace App\Http\Controllers\Administrator\Content;

use App\Http\Controllers\Controller;
use App\Models\NewsCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NewsCategoryController extends Controller
{
    /**
     * Список ссылок
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $groups = NewsCategory::all()->map(function($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'color' => $item->color,
                    'locales' => $item->getTranslations(),
                    'count_links' => $item->news?->count()
                ]
            ];
        });

        return response()->json([
            'data' => $groups,
            'total' => count($groups)
        ]);
    }


    /**
     * Обработка и добавление ссылки
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255',
            'color' => 'nullable'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $options = [
            'name' => $request->name,
            'color' => $request->color ?? '',
        ];
        $group = NewsCategory::create($options);


        $groups = NewsCategory::all()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'color' => $item->color,
                'locales' => $item->getTranslations()
            ];
        });

        return response()->json([
            'status' => 0,
            'message' => $group->name .' '. __('успешно добавлен'),
            'updated' => $groups
        ]);
    }

    /**
     * Обработка и обновление группы
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(int $id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255',
            'color' => 'nullable'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $group = NewsCategory::findOrFail($id);


        $options = [
            'name' => $request->name,
            'color' => $request->color ?? '',
        ];

        $group->update($options);


        $groups = NewsCategory::all()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'color' => $item->color,
                'locales' => $item->getTranslations()
            ];
        });


        return response()->json([
            'status' => 0,
            'message' => $group->name .' '. __('успешно обновлен'),
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
        if (config('iexexchanger.is_reading_mode'))
        {
            return response()->json([
                'status' => 1,
                'message' => 'В demo версии данная функция недоступна'
            ]);
        }

        $item = NewsCategory::findOrFail($id);
        $oldItem = $item;
        if ($item->news->count() > 0)
        {
            return response()->json([
                'status' => 1,
                'message' => 'Группу невозможно удалить, к ней привязаны новости'
            ]);
        }

        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name .' успешно удален'
        ]);
    }
}
