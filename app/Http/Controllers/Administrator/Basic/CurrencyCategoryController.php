<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\CurrencyCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CurrencyCategoryController extends Controller
{
    /**
     * Получить список категорий валют.
     *
     * Дополнительный режим:
     *  - ?is_loading_currencies=1  => отдаёт список валют для SmartMultiSelect
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Вспомогательная загрузка валют для мультиселекта
        if ($request->has('is_loading_currencies')) {
            $items = Currency::query()
                ->select('id', 'tech_name')
                ->orderBy('tech_name')
                ->get()
                ->map(fn ($item) => [
                    'id' => (int) $item->id,
                    'value' => (string) $item->tech_name,
                ])
                ->values()
                ->all();

            return response()->json(['items' => $items]);
        }

        // Основной список категорий
        $items = CurrencyCategory::query()
            ->orderBy('position')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function (CurrencyCategory $category) {
                return [
                    'id' => (int) $category->id,
                    'attributes' => [
                        'code' => (string) $category->code,
                        'title' => $category->title,
                        'position' => (int) $category->position,
                        'is_active' => (bool) $category->is_active,
                        'created_at' => optional($category->created_at)?->translatedFormat('d M Y H:i'),
                        'created_at_human' => optional($category->created_at)?->diffForHumans(),
                        'updated_at' => optional($category->updated_at)?->translatedFormat('d M Y H:i'),
                        'updated_at_human' => optional($category->updated_at)?->diffForHumans(),
                    ],
                ];
            });

        return response()->json([
            'data' => $items,
            'total' => $items->count(),
        ]);
    }

    /**
     * Создать категорию.
     *
     * Важное правило:
     *  - CODE генерируется ТОЛЬКО при создании и дальше НЕ меняется.
     *  - Если одинаковые названия, code станет уникальным: popular, popular_2, popular_3...
     *
     * Ограничение:
     *  - Одна валюта может принадлежать только одной категории.
     *
     * Можно передать:
     *  - title (обязательно для default_locale)
     *  - is_active (опционально)
     *  - currency_ids (опционально) — список валют, которые будут добавлены в категорию
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $defaultLocale = (string) config('iexexchanger.default_locale');

        $validator = Validator::make($request->all(), [
            'title.' . $defaultLocale => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],

            'currency_ids' => ['nullable', 'array'],
            'currency_ids.*' => ['integer', 'exists:currencies,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        // Привязка валют (опционально)
        $currencyIds = collect($request->input('currency_ids', []))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values();

        // Запрет: валюта не может быть в другой категории
        $conflict = $this->findCurrencyCategoryConflict($currencyIds->all(), null);

        if ($conflict !== null) {
            return response()->json([
                'status' => 1,
                'message' => sprintf(
                    'Валюта уже привязана к категории «%s». Сначала удалите её из этой категории.',
                    $conflict['category_title']
                ),
            ]);
        }

        // position: ставим автоматически в конец списка
        $nextPosition = (int) (CurrencyCategory::query()->max('position') ?? 0) + 1;

        // code: строим из title[default_locale], с транслитерацией и уникализацией
        $titles = (array) $request->input('title', []);
        $titleDefault = (string) ($titles[$defaultLocale] ?? '');
        $code = $this->uniqueCategoryCodeFromTitle($titleDefault);

        /** @var CurrencyCategory $category */
        $category = null;

        DB::transaction(function () use ($code, $titles, $nextPosition, $request, $currencyIds, &$category) {
            $category = CurrencyCategory::create([
                'code' => $code,
                'title' => $titles,
                'position' => $nextPosition,
                'is_active' => (bool) $request->input('is_active', true),
            ]);

            if ($currencyIds->isEmpty()) {
                return;
            }

            $syncData = [];
            foreach ($currencyIds as $pos => $currencyId) {
                $syncData[$currencyId] = [
                    'position' => (int) $pos,
                    'is_active' => 1,
                ];
            }

            $category->currencies()->sync($syncData);
        });

        return response()->json([
            'status' => 0,
            'message' => 'Категория успешно добавлена',
            'id' => (int) $category->id,
            'usage' => [
                'currency_ids' => $category->currencies()
                    ->pluck('currencies.id')
                    ->map(fn ($v) => (int) $v)
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /**
     * Получить данные категории для формы редактирования.
     *
     * @param  Request  $request
     * @param  int      $id
     * @return JsonResponse
     */
    public function edit(Request $request, int $id): JsonResponse
    {
        /** @var CurrencyCategory $category */
        $category = CurrencyCategory::query()
            ->with(['currencies' => function ($q) {
                $q->select('currencies.id', 'currencies.tech_name')
                    ->orderBy('currency_category_items.position');
            }])
            ->findOrFail($id);

        return response()->json([
            'id' => (int) $category->id,
            'attributes' => [
                'code' => (string) $category->code, // показывать можно, но НЕ редактируем
                'title' => method_exists($category, 'getTranslations')
                    ? $category->getTranslations('title') // для FormLanguageInput нужен массив переводов
                    : $category->title,
                'position' => (int) $category->position, // только для сортировки/инфо
                'is_active' => (bool) $category->is_active,
                'created_at' => optional($category->created_at)?->translatedFormat('d M Y H:i'),
                'updated_at' => optional($category->updated_at)?->translatedFormat('d M Y H:i'),
            ],
            'usage' => [
                'currency_ids' => $category->currencies
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /**
     * Обновить категорию.
     *
     * Важное правило:
     *  - CODE здесь НЕ меняется (закреплён после создания).
     *  - POSITION здесь НЕ меняется (только через reorder()).
     *
     * Ограничение:
     *  - Одна валюта может принадлежать только одной категории.
     *
     * Можно передать:
     *  - title (мультиязычное)
     *  - is_active
     *  - currency_ids (полная синхронизация)
     *  - currency_items (расширенная синхронизация: position + is_active на pivot)
     *
     * @param  Request  $request
     * @param  int      $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $defaultLocale = (string) config('iexexchanger.default_locale');

        /** @var CurrencyCategory $category */
        $category = CurrencyCategory::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title.' . $defaultLocale => ['sometimes', 'required', 'string', 'max:255'],
            'title' => ['sometimes', 'array'],
            'is_active' => ['sometimes', 'boolean'],

            // простой режим
            'currency_ids' => ['nullable', 'array'],
            'currency_ids.*' => ['integer', 'exists:currencies,id'],

            // расширенный режим
            'currency_items' => ['nullable', 'array'],
            'currency_items.*.currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'currency_items.*.position' => ['nullable', 'integer', 'min:0'],
            'currency_items.*.is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $update = [];

        if ($request->has('title')) {
            $update['title'] = (array) $request->input('title', []);
        }

        if ($request->has('is_active')) {
            $update['is_active'] = (bool) $request->input('is_active', true);
        }

        // --- Проверка конфликтов (одна валюта = одна категория)
        $incomingCurrencyIds = [];

        if ($request->filled('currency_items') && is_array($request->input('currency_items'))) {
            $incomingCurrencyIds = collect($request->input('currency_items', []))
                ->map(fn ($row) => (int) ($row['currency_id'] ?? 0))
                ->filter()
                ->unique()
                ->values()
                ->all();
        } elseif ($request->has('currency_ids')) {
            $incomingCurrencyIds = collect($request->input('currency_ids', []))
                ->map(fn ($v) => (int) $v)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $conflictId = $this->findCurrencyCategoryConflict($incomingCurrencyIds, $category->id);
        if ($conflictId !== null) {
            return response()->json([
                'status' => 1,
                'message' => 'Валюта уже привязана к другой категории (ID: ' . $conflictId . '). Сначала удалите её из другой категории.',
            ]);
        }

        DB::transaction(function () use ($request, $category, $update) {
            if (!empty($update)) {
                $category->update($update);
            }

            // Приоритет: currency_items (с позициями и активностью pivot)
            if ($request->filled('currency_items') && is_array($request->input('currency_items'))) {
                $items = collect($request->input('currency_items', []))
                    ->map(function ($row) {
                        return [
                            'currency_id' => (int) ($row['currency_id'] ?? 0),
                            'position' => isset($row['position']) ? (int) $row['position'] : 0,
                            'is_active' => array_key_exists('is_active', $row) ? (bool) $row['is_active'] : true,
                        ];
                    })
                    ->filter(fn ($row) => $row['currency_id'] > 0)
                    ->unique('currency_id')
                    ->values();

                $syncData = [];
                foreach ($items as $row) {
                    $syncData[$row['currency_id']] = [
                        'position' => $row['position'],
                        'is_active' => $row['is_active'] ? 1 : 0,
                    ];
                }

                $category->currencies()->sync($syncData);
                return;
            }

            // currency_ids (полная синхронизация по списку)
            if ($request->has('currency_ids')) {
                $currencyIds = collect($request->input('currency_ids', []))
                    ->map(fn ($v) => (int) $v)
                    ->filter()
                    ->unique()
                    ->values();

                $syncData = [];
                foreach ($currencyIds as $pos => $currencyId) {
                    $syncData[$currencyId] = [
                        'position' => (int) $pos,
                        'is_active' => 1,
                    ];
                }

                $category->currencies()->sync($syncData);
            }
        });

        return response()->json([
            'status' => 0,
            'message' => 'Категория успешно обновлена',
        ]);
    }

    /**
     * Удалить категорию.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        /** @var CurrencyCategory $category */
        $category = CurrencyCategory::findOrFail($id);

        $attachedCount = $category->currencies()->count();

        DB::transaction(function () use ($category) {
            $category->currencies()->detach();
            $category->delete();
        });

        return response()->json([
            'status' => 0,
            'message' => 'Категория успешно удалена',
            'detached_currencies' => (int) $attachedCount,
        ]);
    }

    /**
     * Массовая сортировка категорий (drag&drop).
     *
     * Body: { ids: [3,1,2] }
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function reorder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:currency_categories,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $ids = collect($request->input('ids', []))
            ->map(fn ($v) => (int) $v)
            ->values();

        DB::transaction(function () use ($ids) {
            foreach ($ids as $pos => $id) {
                CurrencyCategory::whereKey($id)->update(['position' => (int) $pos]);
            }
        });

        return response()->json([
            'status' => 0,
            'message' => 'Сортировка сохранена',
        ]);
    }

    /**
     * Сохранение порядка валют внутри категории (drag&drop).
     *
     * Ограничение:
     *  - Одна валюта может принадлежать только одной категории.
     *
     * Body: { ids: [10,5,7] }
     *
     * @param  Request  $request
     * @param  int      $id
     * @return JsonResponse
     */
    public function syncCurrencies(Request $request, int $id): JsonResponse
    {
        /** @var CurrencyCategory $category */
        $category = CurrencyCategory::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:currencies,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $ids = collect($request->input('ids', []))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values();

        // Запрет: валюта не может быть в другой категории
        $conflictId = $this->findCurrencyCategoryConflict($ids->all(), $category->id);
        if ($conflictId !== null) {
            return response()->json([
                'status' => 1,
                'message' => 'Валюта уже привязана к другой категории (ID: ' . $conflictId . '). Сначала удалите её из другой категории.',
            ]);
        }

        DB::transaction(function () use ($category, $ids) {
            $syncData = [];
            foreach ($ids as $pos => $currencyId) {
                $syncData[$currencyId] = [
                    'position' => (int) $pos,
                    'is_active' => 1,
                ];
            }
            $category->currencies()->sync($syncData);
        });

        return response()->json([
            'status' => 0,
            'message' => 'Сортировка валют сохранена',
        ]);
    }

    /**
     * Привести название категории к базовому коду:
     *  - транслитерация (для кириллицы и т.п.)
     *  - slug (snake через '_')
     *  - очистка до [a-z0-9_]
     *  - fallback, если после нормализации пусто
     *
     * @param  string  $title
     * @return string
     */
    private function normalizeCategoryCodeFromTitle(string $title): string
    {
        $ascii = Str::ascii($title);
        $code = Str::slug($ascii, '_');

        $code = preg_replace('/[^a-z0-9_]/', '', (string) $code) ?? '';
        $code = trim($code, '_');

        if ($code === '') {
            $code = 'category_' . Str::lower(Str::random(6));
        }

        return $code;
    }

    /**
     * Сделать код категории уникальным в таблице currency_categories.
     * Если код уже занят, добавляет суффикс _2, _3, ...
     *
     * @param  string  $title
     * @return string
     */
    private function uniqueCategoryCodeFromTitle(string $title): string
    {
        $base = $this->normalizeCategoryCodeFromTitle($title);

        $code = $base;
        $i = 2;

        while (CurrencyCategory::query()->where('code', $code)->exists()) {
            $code = $base . '_' . $i;
            $i++;
        }

        return $code;
    }

    /**
     * Проверяет, что указанные валюты не привязаны к другой категории.
     *
     * Возвращает данные конфликтующей категории для понятного сообщения.
     *
     * @param  array<int,int>  $currencyIds
     * @param  int|null        $ignoreCategoryId
     * @return array{currency_id:int, category_id:int, category_title:string}|null
     */
    private function findCurrencyCategoryConflict(array $currencyIds, ?int $ignoreCategoryId = null): ?array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $currencyIds))));
        if ($ids === []) {
            return null;
        }

        /** @var CurrencyCategory|null $category */
        $category = CurrencyCategory::query()
            ->when($ignoreCategoryId !== null, fn ($q) => $q->where('id', '!=', $ignoreCategoryId))
            ->whereHas('currencies', function ($q) use ($ids) {
                $q->whereIn('currencies.id', $ids);
            })
            ->with(['currencies' => function ($q) use ($ids) {
                // Подтягиваем только конфликтующие валюты и берём первую
                $q->select('currencies.id')
                  ->whereIn('currencies.id', $ids)
                  ->limit(1);
            }])
            ->first();

        if (! $category) {
            return null;
        }

        $currencyId = (int) optional($category->currencies->first())->id;
        if ($currencyId <= 0) {
            return null;
        }

        // title может быть строкой текущей локали (Spatie) или массивом переводов
        $title = $category->title;
        $defaultLocale = (string) config('iexexchanger.default_locale');

        $categoryTitle = '';
        if (is_string($title)) {
            $categoryTitle = $title;
        } elseif (is_array($title)) {
            $categoryTitle = (string) ($title[$defaultLocale] ?? reset($title) ?? '');
        }

        return [
            'currency_id' => $currencyId,
            'category_id' => (int) $category->id,
            'category_title' => $categoryTitle,
        ];
    }
}
