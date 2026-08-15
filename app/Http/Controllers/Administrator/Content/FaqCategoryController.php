<?php
/**
 * Created by PhpStorm.
 * User: steei
 * Date: 19.10.2019
 * Time: 15:20
 */

namespace App\Http\Controllers\Administrator\Content;

use App\Http\Controllers\Controller;
use App\Models\FaqCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FaqCategoryController extends Controller
{
    /**
     * Список категорий вопросов и ответов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $categories = FaqCategory::query()
            ->withCount('faq')
            ->orderBy('sorting')
            ->orderBy('id')
            ->get()
            ->map(function (FaqCategory $item) {
                return [
                    'id' => $item->id,
                    'attributes' => [
                        'name' => $item->name,
                        'locales' => $item->getTranslations(),
                        'status' => (int) ($item->status ?? 0),
                        'count_faq' => (int) ($item->faq_count ?? 0),
                    ]
                ];
            });

        return response()->json([
            'data' => $categories,
            'total' => count($categories)
        ]);
    }

    /**
     * Обработка и добавление категорий
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

        FaqCategory::create([
            'name' => $request->name,
            'status' => 1,
        ]);

        $categories = FaqCategory::query()
            ->orderBy('sorting')
            ->orderBy('id')
            ->get()
            ->map(function (FaqCategory $item) {
                return [
                    'id' => (int) $item->id,
                    'name' => $item->name,
                    'sorting' => (int) ($item->sorting ?? 0),
                    'status' => (int) ($item->status ?? 0),
                    'locales' => $item->getTranslations(),
                ];
            });

        return response()->json([
            'status' => 0,
            'message' => 'Категория успешно добавлена',
            'updated' => $categories
        ]);

    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $group = FaqCategory::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name.'.config('iexexchanger.default_locale') => 'required|max:255',
            'status' => 'nullable|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $group->update([
            'name' => $request->name,
            'status' => (int) ($request->input('status', $group->status ?? 0)),
        ]);

        $categories = FaqCategory::query()
            ->orderBy('sorting')
            ->orderBy('id')
            ->get()
            ->map(function (FaqCategory $item) {
                return [
                    'id' => (int) $item->id,
                    'name' => $item->name,
                    'sorting' => (int) ($item->sorting ?? 0),
                    'status' => (int) ($item->status ?? 0),
                    'locales' => $item->getTranslations(),
                ];
            });

        return response()->json([
            'status' => 0,
            'message' => "Категория {$group->name} успешно обновлена",
            'updated' => $categories
        ]);
    }

    /**
     * Удаление уведомлений
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy(int $id)
    {
        if (config('iexexchanger.is_reading_mode')) {
            return response()->json([
                'status' => 1,
                'message' => 'В demo версии данная функция недоступна'
            ]);
        }
        $item = FaqCategory::findOrFail($id);
        $oldItem = $item;
        if ($item->faq->count() > 0) {

            return response()->json([
                'status' => 1,
                'message' => 'Категорию невозможно удалить, к ней привязаны вопросы и ответы'
            ]);
        }

        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name .' успешно удален'
        ]);
    }
}
