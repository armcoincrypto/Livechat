<?php

namespace iEXPackages\ExchangerClient\Http\Resources\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NewsResources extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return Collection
     */
    public function toArray(Request $request): Collection
    {
        return $this->collection->map(function ($item)
        {
            return [
                'id' => $item->id,
                'attributes' => [
                    'title' => $item->name,
                    'slug' => sprintf('%s-%s', $item->id, $item->parent_url),
                    'category' => [
                        'name' => isset($item->category) ? $item->category->name : 'News',
                        'color' => isset($item->category) ? '#' . $item->category->color : '',
                    ],
                    'body' => $item->text,
                    'parent_url' => $item->parent_url,
                    'image' => sprintf('%s/%s/%s', config('app.api_url'), config('image.folders.news'), $item->image),
                    'created_at' => Carbon::parse($item->created_at)->timestamp * 1000
                ]
            ];
        });
    }
}
