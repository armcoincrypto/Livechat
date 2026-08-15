<?php

namespace iEXPackages\ExchangerClient\Http\Resources\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Homepage/rates SSR payload: news cards need metadata + short excerpt only.
 * Full HTML bodies stay on NewsResource (article show) and NewsResources (news list API).
 */
class NewsHomepageCardResources extends ResourceCollection
{
    private const EXCERPT_MAX_LENGTH = 500;

    public function toArray(Request $request): Collection
    {
        return $this->collection->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'title' => $item->name,
                    'slug' => sprintf('%s-%s', $item->id, $item->parent_url),
                    'category' => [
                        'name' => isset($item->category) ? $item->category->name : 'News',
                        'color' => isset($item->category) ? '#' . $item->category->color : '',
                    ],
                    // Trimmed for SSR: Angular homepage cards use readmore on excerpt, not full article HTML.
                    'body' => self::excerptForHomepage($item->text),
                    'parent_url' => $item->parent_url,
                    'image' => sprintf('%s/%s/%s', config('app.api_url'), config('image.folders.news'), $item->image),
                    'created_at' => Carbon::parse($item->created_at)->timestamp * 1000,
                ],
            ];
        });
    }

    private static function excerptForHomepage(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return null;
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) <= self::EXCERPT_MAX_LENGTH) {
            return $text;
        }

        return mb_substr($text, 0, self::EXCERPT_MAX_LENGTH) . '...';
    }
}
