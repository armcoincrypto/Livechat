<?php

namespace iEXPackages\ExchangerClient\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class LinksReviewResources extends ResourceCollection
{
    public function toArray($request)
    {
        return $this->collection->map(function ($item) {
            return [
                'id' => $item->id,
                'icon' => !empty($item->icon) ? sprintf('%s/%s/%s', config('app.api_url'), config('image.folders.link_review'), $item->icon) : null,
                'url' => $item->url,
                'count' => $item->count_review,
            ];
        });
    }
}
