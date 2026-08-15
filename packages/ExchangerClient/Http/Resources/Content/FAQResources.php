<?php

namespace iEXPackages\ExchangerClient\Http\Resources\Content;

use Illuminate\Http\Resources\Json\ResourceCollection;

class FAQResources extends ResourceCollection
{

    public function toArray($request) {

        return $this->collection->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'sorting' => $item->sorting,
                'text' => $item->text,
            ];
        });
    }
}
