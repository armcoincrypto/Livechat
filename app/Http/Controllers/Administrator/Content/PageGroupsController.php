<?php
declare(strict_types=1);

namespace App\Http\Controllers\Administrator\Content;

use App\Http\Controllers\Controller;
use App\Models\PageGroup;
use App\Models\Page;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PageGroupsController extends Controller
{
    /**
     * Список групп
     */
    public function index(Request $request): JsonResponse
    {
        $items = PageGroup::query()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'status' => 0,
            'items' => [
                'data' => $items->map(static function (PageGroup $group) {
                    return [
                        'id' => (int) $group->id,
                        'attributes' => [
                            'locales' => $group->getTranslations(),
                            'slug' => (string) $group->slug,
                            'title' => $group->title,
                            'description' => $group->description,
                            'is_active' => (bool) $group->is_active,
                            'created_at' => $group->created_at?->toIso8601String(),
                            'updated_at' => $group->updated_at?->toIso8601String(),
                        ],
                    ];
                })->values(),
                'meta' => [
                    'total' => $items->count(),
                ],
            ],
        ]);
    }

    /**
     * Создание группы
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title.' . config('iexexchanger.default_locale') => 'required',
            'description' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }
        $payload = $validator->validated();

        /** @var PageGroup|null $item */
        $item = null;

        $title = (array) ($payload['title'] ?? []);

        DB::transaction(function () use (&$item, $title, $payload): void {
            $item = PageGroup::query()->create([
                'slug' => 'tmp-' . Str::lower(Str::random(20)),
                'title' => $title,
                'description' => $payload['description'] ?? null,
                'is_active' => (bool) ($payload['is_active'] ?? true),
            ]);

            $baseTitle = (string) (
                $title[config('iexexchanger.default_locale')]
                ?? Arr::first($title)
                ?? 'group'
            );

            $baseSlug = Str::slug($baseTitle);
            if ($baseSlug === '') {
                $baseSlug = 'group';
            }

            $slug = $this->uniqueGroupSlug($baseSlug, $item->id);

            $item->update(['slug' => $slug]);
        });

        return response()->json([
            'status' => 0,
            'message' => __('Группа страниц успешно добавлена'),
        ]);
    }

    /**
     * Данные группы для формы
     */
    public function edit(int $id): JsonResponse
    {
        $item = PageGroup::query()->findOrFail($id);

        return response()->json([
            'status' => 0,
            'id' => (int) $item->id,
            'attributes' => [
                'slug' => (string) $item->slug,
                'title' => $item->title,
                'description' => $item->description,
                'is_active' => (bool) $item->is_active,
                'locales' => $item->getTranslations(),
                'created_at' => $item->created_at?->toIso8601String(),
                'updated_at' => $item->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Обновление группы
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $item = PageGroup::query()->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title.' . config('iexexchanger.default_locale') => 'required',
            'description' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }
        $payload = $validator->validated();

        $title = (array) ($payload['title'] ?? []);

        DB::transaction(function () use ($item, $title, $payload): void {
            $item->update([
                'title' => $title,
                'description' => array_key_exists('description', $payload) ? $payload['description'] : $item->description,
                'is_active' => array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : (bool) $item->is_active,
            ]);

            $baseTitle = (string) (
                $title[config('iexexchanger.default_locale')]
                ?? Arr::first($title)
                ?? ''
            );

            $baseSlug = Str::slug($baseTitle);
            if ($baseSlug === '') {
                return;
            }

            $newSlug = $this->uniqueGroupSlug($baseSlug, $item->id);
            if ($newSlug !== (string) $item->slug) {
                $item->update(['slug' => $newSlug]);
            }
        });

        return response()->json([
            'status' => 0,
            'message' => __('Группа страниц успешно обновлена'),
        ]);
    }

    /**
     * Удаление группы
     */
    public function destroy(int $id): JsonResponse
    {
        $item = PageGroup::query()->findOrFail($id);

        $hasPages = Page::query()
            ->where('group_id', (int) $item->id)
            ->limit(1)
            ->exists();

        if ($hasPages) {
            return response()->json([
                'status' => 1,
                'message' => __('Нельзя удалить группу: к ней привязаны страницы. Сначала перенесите или удалите страницы из этой группы.'),
            ]);
        }

        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => __('Группа страниц удалена'),
        ]);
    }

    /**
     * Генерация уникального slug
     */
    private function uniqueGroupSlug(string $baseSlug, ?int $ignoreId = null): string
    {
        $slug = $baseSlug;
        $i = 2;

        while (true) {
            $q = PageGroup::query()->where('slug', $slug);
            if ($ignoreId !== null) {
                $q->where('id', '!=', $ignoreId);
            }

            if (!$q->exists()) {
                return $slug;
            }

            $slug = $baseSlug . '-' . $i;
            $i++;
        }
    }
}
