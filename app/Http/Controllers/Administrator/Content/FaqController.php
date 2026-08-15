<?php

namespace App\Http\Controllers\Administrator\Content;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FaqController extends Controller
{
    /**
     * Список вопросов и ответов (группы = категории)
     */
    /**
     * Список вопросов и ответов (группы = категории)
     */
    public function index(Request $request): JsonResponse
    {
        // Категории (группы) — стабильный порядок
        $categoryModels = FaqCategory::query()
            ->orderBy('sorting')
            ->orderBy('id')
            ->get();

        // Плоский список категорий для форм (UiSmartListPopover и т.п.)
        $categories = $categoryModels->map(static function (FaqCategory $item) {
            return [
                'id' => (int) $item->id,
                'name' => (string) $item->name,
                'sorting' => (int) ($item->sorting ?? 0),
                'status' => (int) ($item->status ?? 0),
                'locales' => $item->getTranslations(),
            ];
        })->values();

        // FAQ: стабильный порядок внутри групп
        $faqAll = Faq::query()
            ->filter($request->all())
            ->orderBy('id_group')
            ->orderBy('sorting')
            ->orderBy('id')
            ->get();

        $total = $faqAll->count();

        // Группируем по id_group (категория)
        $faqByGroupId = $faqAll->groupBy(static fn (Faq $f) => (int) ($f->id_group ?? 0));

        // Группы: категории + items
        $groups = $categoryModels->map(static function (FaqCategory $cat) use ($faqByGroupId) {
            $items = ($faqByGroupId->get((int) $cat->id) ?? collect())
                ->map(static function (Faq $item) use ($cat) {
                    return [
                        'id' => (int) $item->id,
                        'attributes' => [
                            'title' => $item->title,
                            'status' => (int) $item->status,
                            'created_at' => $item->created_at?->toIso8601String(),
                            'updated_at' => $item->updated_at?->toIso8601String(),
                            'category' => [
                                'id' => (int) $cat->id,
                                'name' => (string) $cat->name,
                            ],
                        ],
                    ];
                })
                ->values();

            return [
                'id' => (int) $cat->id,
                'name' => (string) $cat->name,
                'sorting' => (int) ($cat->sorting ?? 0),
                'status' => (int) ($cat->status ?? 0),
                'items' => $items,
            ];
        })->values();

        return response()->json([
            'groups' => $groups,
            'total' => $total,
            'categories' => $categories,
        ]);
    }

    /**
     * Обработка и добавление вопросов и ответов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title.'.config('iexexchanger.default_locale') => 'required|max:255',
            'id_group' => ['required', 'exists:faq_category,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }


        $options = [
            'id_group' => (int)$request->get('id_group'),
            'status' => (int)$request->has('status') ? $request->get('status') : 0,
            'title' => $request->title,
            'text' => cleanHtmlContent($request->text)
        ];

        $faq = Faq::create($options);
        return response()->json([
            'status' => 0,
            'message' => $faq->title .' успешно добавлен'
        ]);
    }

    /**
     * Форма редактирования вопросов и ответов
     *
     * @param int $id
     * @return JsonResponse
     */
    public function edit(int $id)
    {
        $item = Faq::findOrFail($id);
        $categories = FaqCategory::orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'locales' => $item->getTranslations()
            ];
        });

       return response()->json([
           'id' => $item->id,
           'attributes' => [
               'locales' => $item->getTranslations(),
               'title' => $item->title,
               'text' => $item->text,
               'status' => (bool)$item->status,
               'id_group' => $item->id_group,
           ],

          'categories' => $categories
       ]);
    }

    /**
     * Обработка и редактирование вопросов и ответов
     *
     * @return JsonResponse
     */
    public function update(Request $request, $id)
    {

        $validator = Validator::make($request->all(), [
            'title.'.config('iexexchanger.default_locale') => 'required|max:255',
            'id_group' => ['required', 'exists:faq_category,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $options = [
            'id_group' => (int)$request->get('id_group'),
            'status' => (int)$request->has('status') ? $request->get('status') : 0,
            'title' => $request->title,
            'text' => cleanHtmlContent($request->text)
        ];

        $faq = Faq::findOrFail($id);
        $faq->update($options);

        return response()->json([
            'status' => 0,
            'message' => $faq->title .' успешно обновлен'
        ]);
    }

    /**
     * Обновить статус (вкл/выкл) для FAQ
     * POST: id, status (0|1)
     */
    public function updateItemStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer', 'exists:faq,id'],
            'status' => ['required', 'integer', Rule::in([0, 1])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $id = (int) $request->integer('id');
        $status = (int) $request->integer('status');

        Faq::query()->whereKey($id)->update(['status' => $status]);

        return response()->json([
            'status' => 0,
            'message' => 'Статус обновлен',
        ]);
    }

    /**
     * Обновить статус (вкл/выкл) для категории FAQ
     * POST: id, status (0|1)
     */
    public function updateCategoryStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer', 'exists:faq_category,id'],
            'status' => ['required', 'integer', Rule::in([0, 1])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $id = (int) $request->integer('id');
        $status = (int) $request->integer('status');

        FaqCategory::query()->whereKey($id)->update(['status' => $status]);

        return response()->json([
            'status' => 0,
            'message' => 'Статус обновлен',
        ]);
    }

    /**
     * Удаление вопросов и ответов
     *
     * @param int $id
     * @return JsonResponse
     *
     */
    public function destroy(int $id): JsonResponse
    {
        $faq = Faq::findOrFail($id);
        $oldItem = $faq;
        $faq->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->title .' успешно удален'
        ]);
    }


}
