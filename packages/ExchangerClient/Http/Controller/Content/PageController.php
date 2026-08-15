<?php

declare(strict_types=1);

namespace iEXPackages\ExchangerClient\Http\Controller\Content;

use App\Models\Page;
use App\Models\PageGroup;
use Illuminate\Http\JsonResponse;

class PageController
{
    public function show(string $slug): JsonResponse
    {
        $safeSlug = $this->sanitizeSlug($slug);

        // 1) Находим страницу по slug (активную)
        // Важно: если у тебя slug может повторяться в разных группах — этот вариант не годится.
        // Тогда нужен роут /pages/{group}/{slug} как основной.
        $page = Page::query()
            ->where('page_slug', $safeSlug)
            ->where('is_active', true)
            ->first();

        if (!$page) {
            return response()->json(['error' => 'Page not found'], 404);
        }

        $typeContent = (int) iEXSetting('is_enable_page_content_accordion');

        // 2) Если страница НЕ в группе — отдаём как обычную
        if ($page->group_id === null) {
            return response()->json([
                'mode' => 'single',
                'type_content' => $typeContent,
                'page' => [
                    'title' => $page->page_title,
                    'headline' => $page->page_headline,
                    'content' => $this->replacePlaceholders((string) $page->page_content),
                ],
            ]);
        }

        // 3) Страница в группе — грузим группу + sidebar
        $group = PageGroup::query()
            ->whereKey((int) $page->group_id)
            ->where('is_active', true)
            ->first();

        // если группу выключили/удалили — можно вернуть single (или 404, как решишь)
        if (!$group) {
            return response()->json([
                'mode' => 'single',
                'type_content' => $typeContent,
                'page' => [
                    'title' => $page->page_title,
                    'headline' => $page->page_headline,
                    'content' => $this->replacePlaceholders((string) $page->page_content),
                ],
            ]);
        }

        $sidebar = Page::query()
            ->where('group_id', $group->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('page_id')
            ->get(['page_id', 'page_slug', 'page_title'])
            ->map(static function (Page $p) {
                return [
                    'id' => (int) $p->page_id,
                    'slug' => (string) $p->page_slug,
                    'title' => $p->page_title,
                ];
            })
            ->values();

        return response()->json([
            'mode' => 'group',
            'type_content' => $typeContent,
            'group' => [
                'id' => (int) $group->id,
                'slug' => (string) $group->slug,
                'title' => $group->title,
                'description' => $group->description,
            ],
            'sidebar' => $sidebar,
            'page' => [
                'title' => $page->page_title,
                'headline' => $page->page_headline,
                'content' => $this->replacePlaceholders((string) $page->page_content),
            ],
        ]);
    }

    /**
     * Безопасная обработка slug
     */
    private function sanitizeSlug(string $slug): string
    {
        $safe = security_xss($slug);
        return trim((string) $safe);
    }

    /**
     * Замена плейсхолдеров в тексте страницы
     */
    private function replacePlaceholders(string $text): string
    {
        return strtr($text, [
            '{sitename}'      => iEXContentLanguage('sitename'),
            '{sitename_desc}' => iEXContentLanguage('sitename_desc'),
        ]);
    }
}
