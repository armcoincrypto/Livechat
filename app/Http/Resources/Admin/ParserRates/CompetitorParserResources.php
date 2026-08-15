<?php

namespace App\Http\Resources\Admin\ParserRates;

use Illuminate\Http\Request;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class CompetitorParserResources extends ResourceCollection
{
    private $pagination;


    public function __construct($resource)
    {
        $this->pagination = [
            'total' => $resource->total(),
            'per_page' => $resource->perPage(),
            'current_page' => $resource->currentPage(),
            'from' => $resource->firstItem(),
            'to' => $resource->lastItem(),
            'last_page' => $resource->lastPage(),
        ];

        $resource = $resource->getCollection(); // Necessary to remove meta and links

        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($item) {
                return [
                    'id' => $item->id,
                    'attributes' => [
                        'name' => $item->name,
                        'status' => (bool)$item->status,
                        'code'  => $item->code,
                        'exchange_in' => $item->exchange_in,
                        'exchange_out' => $item->exchange_out,
                        'id_competitor' => $item->id_competitor,
                        'value' => $item->value,
                        'summa' => $item->summa,
                        'group' => [
                            'id' => $item->competitor_link?->id,
                            'name' => $item->competitor_link?->name,
                        ],

                        'direction_exchange' => $item->direction_exchange->count() > 0 ? $item->direction_exchange->map(function($item) {
                            return [
                                'id' => $item->id,
                                'name' => $item->tech_name
                            ];
                        }) : [],

                        'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                        'created_at_human' => $item->created_at->diffForHumans(),
                        'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                        'updated_at_human' => $item->updated_at->diffForHumans(),
                    ],
                ];
            }),
            ...$this->pagination
        ];
    }
}
