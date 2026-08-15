<?php

namespace iEXPackages\ExchangerClient\Http\Resources\Contact;

use Illuminate\Http\Resources\Json\ResourceCollection;

class LinksReviewGroupResources extends ResourceCollection
{
    public function toArray($request)
    {
        return $this->collection->map(function ($link) {
            return [
                'id' => $link->id,
                'name' => $link->name,
                'reviewLarge' => isset($link->links_review_large) ? $link->links_review_large->map(function ($link) {
                    return [
                        'id' => $link->id,
                        'name' => $link->name,
                        'url' => $link->url,
                        'sorting' => $link->sorting,
                        'description' => $link->description,
                        'count_review' => $link->count_review,
                        'icon' => !empty($link->icon) ? sprintf('%s/%s/%s', config('app.api_url'), config('image.folders.link_review'), $link->icon) : null
                    ];
                }) : [],

                'reviewDefault' => isset($link->links_review_url) ? $link->links_review_url->map(function ($link) {
                    return [
                        'id' => $link->id,
                        'name' => $link->name,
                        'url' => $link->url,
                        'sorting' => $link->sorting,
                        'description' => $link->description,
                        'count_review' => $link->count_review,
                        'icon' => $link->icon,
                    ];
                }): [],
            ];
        });
    }
}
