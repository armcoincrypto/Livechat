<?php

namespace iEXPackages\ExchangerClient\Http\Resources\Content;

use Illuminate\Http\Resources\Json\ResourceCollection;

class FAQCategoryResources extends ResourceCollection
{
    public function toArray($request) {

        return $this->collection->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'faq' => isset($item->faq) ? new FAQResources($item->faq) : [],
            ];
        });
    }
}
