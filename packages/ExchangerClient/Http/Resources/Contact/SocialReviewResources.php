<?php

namespace iEXPackages\ExchangerClient\Http\Resources\Contact;

use Illuminate\Http\Resources\Json\ResourceCollection;

class SocialReviewResources extends ResourceCollection
{
    public function toArray($request)
    {
        return $this->collection->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->type,
                'link' => $item->link,
                'status' => $item->status,
                'sorting' => $item->sorting,
            ];
        });
    }
}
