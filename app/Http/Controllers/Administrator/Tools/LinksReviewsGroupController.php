<?php

namespace App\Http\Controllers\Administrator\Tools;

use App\Http\Controllers\Controller;
use App\Models\LinksReviewGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LinksReviewsGroupController extends Controller
{
    /**
     * Список ссылок
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $groups = LinksReviewGroup::orderBy('sorting')->get()->map(function($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'locales' => $item->getTranslations(),
                    'count_links' => $item->links_review->count()
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
            'name.'.config('iexexchanger.default_locale') => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $options = [
            'name' => $request->name
        ];
        $group = LinksReviewGroup::create($options);


        $groups = LinksReviewGroup::orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
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
     * Форма редактирования группы
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit(int $id)
    {
        $link = LinksReviewGroup::findOrFail($id);

        return view('admin.tools.links_reviews.groups.edit', compact('link'));
    }

    /**
     * Обработка и обновление группы
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(int $id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $group = LinksReviewGroup::findOrFail($id);


        $options = [
            'name' => $request->name
        ];

        $group->update($options);


        $groups = LinksReviewGroup::orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
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

        $item = LinksReviewGroup::findOrFail($id);
        $oldItem = $item;
        if ($item->links_review->count() > 0)
        {
            return response()->json([
                'status' => 1,
                'message' => 'Группу невозможно удалить, к ней привязаны ссылки'
            ]);
        }

        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name .' успешно удален'
        ]);
    }
}
