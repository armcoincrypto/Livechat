<?php

namespace App\Http\Resources;

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
                    'slug' => $item->id.'-'.$item->parent_url,
                    'body' => $item->text,
                    'short_text' => '',
                    'parent_url' => $item->parent_url,
                    'image' => '/storage/news/'.$item->image,
                    'created_at' => Carbon::parse($item->created_at)->timestamp * 1000,
                ]
            ];
        });
    }
}
