<?php

namespace App\Http\Controllers\Administrator\Tools;

use App\Http\Controllers\Controller;
use App\Models\CheckboxAgreement;
use App\Models\DirectionExchange;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CheckboxAgreementController extends Controller
{
    /**
     * Получение списка чекбоксов и страниц для их редактирования.
     */
    public function index(Request $request)
    {
        // Отдельный метод загрузки направлений обмена (простой вариант Laravel)
        if ($request->boolean('is_loading_direction')) {
            $items = DirectionExchange::query()
                ->select('id', 'tech_name')
                ->orderBy('id')
                ->get()
                ->map(fn ($item) => ['id' => $item->id, 'value' => $item->tech_name])
                ->values();

            return response()->json(['items' => $items], 200, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        }


        // Список всех соглашений с чекбоксами
        $items = CheckboxAgreement::with('page')->orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'label' => $item->label,
                    'checked' => (bool)$item->checked,
                    'required' => (bool)$item->required,
                    'page_type' => $item->page_type,
                    'link' => $item->page_type === 'manual'
                        ? $item->link
                        : ($item->page ? config('app.frontend_url') . '/pages/' . $item->page->page_slug : null),
                    'is_protected' => (bool)$item->is_protected,
                    'status' => (bool)$item->status,
                    'sorting' => $item->sorting,
                    'page_name' => $item->page?->page_slug,
                    'key_id' => $item->key_id,
                    'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                    'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                ]
            ];
        });

        $pages = Page::select('page_id', 'page_title')
            ->get()
            ->map(fn ($item) => ['id' => $item->page_id, 'name' => $item->page_title]);


        return response()->json([
            'data' => $items,
            'pages' => $pages,
            'total' => $items->count(),
        ]);
    }

    /**
     * Создание новой опции чекбокса
     */
    public function store(Request $request)
    {
        if ($request->get('updateField') === 'status') {
            CheckboxAgreement::findOrFail((int)$request->id)->update(['status' => (bool)$request->status]);
            return response()->json(['status' => 0]);
        }


        $validator = Validator::make($request->all(), [
            'label.' . config('iexexchanger.default_locale') => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $checkboxAgreement = CheckboxAgreement::create([
            'label' => $request->label,
            'status' => false,
            'sorting' => CheckboxAgreement::max('sorting') + 1,
        ]);

        return response()->json([
            'status' => 0,
            'message' => $checkboxAgreement->label . ' ' . __('успешно добавлен'),
        ]);
    }

    /**
     * Получение данных чекбокса для редактирования.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id)
    {
        $item = CheckboxAgreement::findOrFail($id);

        // Берём ID напрямую из запроса, без гидрации моделей
        $idsExcluded = $item->excludedDirections()
            ->pluck('direction_exchange.id');

        $idsAllowed = $item->allowedDirections()
            ->pluck('direction_exchange.id');

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'locales' => $item->getTranslations(),
                'label' => $item->label,
                'text_error' => $item->text_error,
                'description' => $item->description,
                'link' => $item->link,
                'page_type' => $item->page_type,
                'page_id' => $item->page_id,
                'checked' => (bool)$item->checked,
                'required' => (bool)$item->required,
                'is_protected' => (bool)$item->is_protected,
                'status' => (bool)$item->status,
                'sorting' => $item->sorting,
                'key_id' => $item->key_id,
                'ids_excluded_directions' => $idsExcluded,
                'ids_allowed_directions' => $idsAllowed,
                'apply_mode' => $item->apply_mode ?? 'all_except',
            ]
        ]);
    }

    /**
     * Обновление чекбокса
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id)
    {
        $item = CheckboxAgreement::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'label.'.config('iexexchanger.default_locale') => 'required|string|max:255',

            'page_type' => ['nullable', Rule::in(['manual', 'page'])],

            'link' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $request->page_type === 'manual'),
            ],

            'page_id' => [
                'nullable',
                'exists:pages,page_id',
                Rule::requiredIf(fn () => $request->page_type === 'page'),
            ],

            'ids_excluded_directions'   => 'nullable|array',
            'ids_excluded_directions.*' => 'exists:direction_exchange,id',
            'ids_allowed_directions'    => 'nullable|array',
            'ids_allowed_directions.*'  => 'exists:direction_exchange,id',
            'apply_mode'                => 'required|in:all_except,only_selected',
            'key_id'                    => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $keyId = Str::lower(preg_replace('/[^a-z0-9_]/', '', $request->key_id));

        if (CheckboxAgreement::where('key_id', $keyId)->where('id', '!=', $id)->exists()) {
            return response()->json([
                'status' => 1,
                'message' => __('Ключ ":key" уже используется другим чекбоксом.', ['key' => $keyId])
            ]);
        }

        $excludedIds = $request->apply_mode === 'all_except'
            ? ($request->ids_excluded_directions ?? [])
            : [];

        $allowedIds = $request->apply_mode === 'only_selected'
            ? ($request->ids_allowed_directions ?? [])
            : [];

        $updateData = [
            'label' => $request->label,
            'description' => $request->description,
            'text_error' => $request->text_error,
            'link' => $request->page_type === 'manual' ? $request->link : null,
            'page_type' => $request->page_type,
            'page_id' => $request->page_type === 'page' ? $request->page_id : null,
            'checked' => (bool)$request->checked,
            'required' => (bool)$request->required,
            'status' => (bool)$request->status,
            'apply_mode' => $request->apply_mode,
            'key_id' => $keyId,
        ];

        $item->update($updateData);
        // Обновляем связи с направлениями согласно режиму применения
        $item->excludedDirections()->sync($excludedIds);
        $item->allowedDirections()->sync($allowedIds);

        return response()->json([
            'status' => 0,
            'message' => $item->label . ' ' . __('успешно обновлен'),
        ]);
    }

    /**
     * Удаление чекбокса (с проверкой на защиту).
     *
     * @param int $id - идентификатор чекбокса для удаления
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        // Получаем чекбокс или выдаём ошибку, если не найден
        $item = CheckboxAgreement::findOrFail($id);

        // Проверка на защиту от удаления
        if ($item->is_protected) {
            return response()->json([
                'status' => 1,
                'message' => __('Удаление этого чекбокса запрещено.')
            ]);
        }

        // Удаление связанных данных (исключённые направления обмена)
        $item->excludedDirections()->detach();
        // Удаление связанных данных (разрешённые направления обмена)
        $item->allowedDirections()->detach();

        // Удаление самого чекбокса
        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $item->label . ' ' . __('успешно удален'),
        ]);
    }
}
