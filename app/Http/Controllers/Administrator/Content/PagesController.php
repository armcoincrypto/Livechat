<?php
declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Content;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Tools\PagesResources;
use App\Models\Page;
use App\Models\PageGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PagesController extends Controller
{
    /**
     * Дополнительные фильтры
     */
    protected array $allowFilteredPage = [
        'exchange_rules_link'
    ];

    /**
     * Список страниц
     */
    public function index(Request $request): JsonResponse
    {
        // Настройки страницы (как было)
        if ($request->has('showPage')) {
            return response()->json([
                'exchange_rules_link' => iEXSetting('exchange_rules_link')
            ]);
        }

        // Если запрошен список групп страниц отдельно
        if ($request->boolean('with_groups')) {
            $groups = PageGroup::query()
                ->orderBy('sort_order')
                ->orderByDesc('id')
                ->get()
                ->map(static function (PageGroup $group) {
                    return [
                        'id' => (int) $group->id,
                        'attributes' => [
                            'slug' => (string) $group->slug,
                            'title' => $group->title,
                            'description' => $group->description,
                            'is_active' => (bool) $group->is_active,
                        ],
                    ];
                })
                ->values();

            // Явный вариант "Без группы" (id = null)
            $withoutGroup = [
                'id' => null,
                'attributes' => [
                    'slug' => null,
                    'title' => __('Без группы'),
                    'description' => null,
                    'is_active' => true,
                ],
            ];

            $groups = collect([$withoutGroup])->merge($groups)->values();

            return response()->json([
                'status' => 0,
                'items' => [
                    'data' => $groups,
                    'meta' => [
                        'total' => $groups->count(),
                    ],
                ],
            ]);
        }

        // Обычный список страниц (как было)
        $pages = Page::query()
            ->orderByDesc('page_id')
            ->paginate(20);

        return response()->json(new PagesResources($pages));
    }

    /**
     * Создание страницы или сохранение настроек (showPage)
     */
    public function store(Request $request): JsonResponse
    {
        // Если включена возможность обновления данных
        if ($request->is_update == 1 && $request->has('showPage')) {

            if ($request->showPage === 'settings') {
                $array = [];
                foreach ($this->allowFilteredPage as $item) {
                    if (isset($request->{$item}) && is_array($request->{$item})) {
                        $array[$item] = implode(',', $request->{$item});
                    } else {
                        $array[$item] = ($request->{$item} ?? '');
                    }
                }
                iEXSetting($array);
            }

            return response()->json([
                'status' => 0,
                'message' => __('Настройки успешно сохранены')
            ]);
        }

        $validator = Validator::make($request->all(), [
            'page_title.' . config('iexexchanger.default_locale') => 'required',
            'page_content.' . config('iexexchanger.default_locale') => 'required',
            'page_headline.' . config('iexexchanger.default_locale') => 'nullable',

            // group_id может быть null/""/0 — exists проверим вручную только если > 0
            'group_id' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $payload = $validator->validated();

        // group_id может быть null/""/0. 0 трактуем как "без группы".
        $groupId = $payload['group_id'] ?? null;
        $groupId = $groupId !== null && $groupId !== '' ? (int) $groupId : null;
        if ($groupId !== null && $groupId <= 0) {
            $groupId = null;
        }

        // existence-check только если реально выбрана группа
        if ($groupId !== null) {
            $exists = PageGroup::query()->whereKey($groupId)->exists();
            if (!$exists) {
                return response()->json([
                    'status' => 1,
                    'message' => __('Выбранная группа страниц не найдена'),
                ]);
            }
        }

        $options = [
            'page_title' => $payload['page_title'],
            'page_content' => normalizeHtmlFieldValue($payload['page_content']),
            'page_headline' => $payload['page_headline'] ?? null,
            'group_id' => $groupId,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'user_id' => $request->user()->id,
        ];

        /** @var Page|null $page */
        $page = null;

        try {
            DB::transaction(function () use (&$page, $options, $groupId): void {
                $page = Page::query()->create(array_merge($options, [
                    'page_slug' => 'tmp-' . Str::lower(Str::random(20)),
                ]));

                $title = (array) ($options['page_title'] ?? []);
                $baseTitle = (string) (
                    $title[config('iexexchanger.default_locale')]
                    ?? Arr::first($title)
                    ?? 'page'
                );

                $baseSlug = Str::slug($baseTitle);
                if ($baseSlug === '') {
                    $baseSlug = 'page';
                }

                $slug = $this->uniquePageSlug($baseSlug, $groupId, (int) $page->page_id);
                $page->update(['page_slug' => $slug]);
            });
        } catch (\Throwable $exception) {
            return response()->json([
                'status' => 1,
                'message' => __('Ошибка при создании страницы, попробуйте еще раз'),
            ]);
        }

        return response()->json([
            'status' => 0,
            'message' => __('Страница успешно добавлена')
        ]);
    }

    /**
     * Форма обновления страницы
     */
    public function edit(int $id): JsonResponse
    {
        $page = Page::query()->findOrFail($id);

        return response()->json([
            'status' => 0,
            'id' => (int) $page->page_id,
            'attributes' => [
                'page_title' => $page->page_title,
                'page_slug' => (string) $page->page_slug,
                'page_headline' => $page->page_headline,
                'page_content' => $page->page_content,
                'group_id' => $page->group_id !== null ? (int) $page->group_id : null,
                'is_active' => (bool) $page->is_active,
                'locales' => $page->getTranslations(),
            ]
        ]);
    }

    /**
     * Обработка и обновление данных
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page_title.' . config('iexexchanger.default_locale') => 'required',
            'page_content.' . config('iexexchanger.default_locale') => 'required',
            'page_headline.' . config('iexexchanger.default_locale') => 'nullable',

            // page_slug можно передать вручную
            'page_slug' => 'nullable|string|max:190',

            // group_id может быть null/""/0 — exists проверим вручную только если > 0
            'group_id' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $payload = $validator->validated();

        $page = Page::query()->findOrFail($id);

        // group_id может быть null/""/0. 0 трактуем как "без группы".
        $groupId = $payload['group_id'] ?? null;
        $groupId = $groupId !== null && $groupId !== '' ? (int) $groupId : null;
        if ($groupId !== null && $groupId <= 0) {
            $groupId = null;
        }

        // existence-check только если реально выбрана группа
        if ($groupId !== null) {
            $exists = PageGroup::query()->whereKey($groupId)->exists();
            if (!$exists) {
                return response()->json([
                    'status' => 1,
                    'message' => __('Выбранная группа страниц не найдена'),
                ]);
            }
        }

        $options = [
            'page_title' => $payload['page_title'],
            'page_content' => normalizeHtmlFieldValue($payload['page_content']),
            'page_headline' => $payload['page_headline'] ?? null,
            'group_id' => $groupId,
            'is_active' => array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : (bool) $page->is_active,
            'user_id' => $request->user()->id,
        ];

        DB::transaction(function () use ($page, $options, $groupId, $payload): void {
            $page->update($options);

            // Если slug передан вручную — используем его. Иначе строим из заголовка.
            $incomingSlug = isset($payload['page_slug']) ? trim((string) $payload['page_slug']) : '';
            $incomingSlug = $incomingSlug !== '' ? Str::slug($incomingSlug) : '';

            if ($incomingSlug !== '') {
                $baseSlug = $incomingSlug;
            } else {
                $title = (array) ($options['page_title'] ?? []);
                $baseTitle = (string) (
                    $title[config('iexexchanger.default_locale')]
                    ?? Arr::first($title)
                    ?? ''
                );

                $baseSlug = Str::slug($baseTitle);
            }

            // Если нечего генерировать — просто выходим (оставляем текущий slug)
            if ($baseSlug === '') {
                return;
            }

            $newSlug = $this->uniquePageSlug($baseSlug, $groupId, (int) $page->page_id);
            if ($newSlug !== (string) $page->page_slug) {
                $page->update(['page_slug' => $newSlug]);
            }
        });

        return response()->json([
            'status' => 0,
            'message' => __('Страница успешно обновлена')
        ]);
    }

    /**
     * Удаление страницы
     */
    public function destroy(int $id): JsonResponse
    {
        $page = Page::query()->findOrFail($id);
        $page->delete();

        return response()->json([
            'status' => 0,
            'message' => __('Страница удалена')
        ]);
    }

    /**
     * Сгенерировать уникальный page_slug в рамках group_id.
     *
     * Учитываем уникальный индекс (group_id, page_slug).
     */
    private function uniquePageSlug(string $baseSlug, ?int $groupId, ?int $ignorePageId = null): string
    {
        $slug = $baseSlug;
        $i = 2;

        while (true) {
            $q = Page::query()->where('page_slug', $slug);

            if ($groupId === null) {
                $q->whereNull('group_id');
            } else {
                $q->where('group_id', $groupId);
            }

            if ($ignorePageId !== null) {
                $q->where('page_id', '!=', $ignorePageId);
            }

            if (!$q->exists()) {
                return $slug;
            }

            $slug = $baseSlug . '-' . $i;
            $i++;
        }
    }
}
