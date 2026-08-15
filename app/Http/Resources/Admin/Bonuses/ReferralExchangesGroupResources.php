<?php

namespace App\Http\Resources\Admin\Bonuses;


use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ReferralExchangesGroupResources extends ResourceCollection
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
            'data' => $this->collection->map(function ($item)
            {
                return [
                    'attributes' => [
                        'user' => isset($item->user_admin) ? [
                            'id' => $item->user_admin->id,
                            'attributes' => [
                                'name' => $item->user_admin->name,
                                'email' => $item->user_admin->email,
                            ]
                        ] : [],

                        'count_num' => $item->count_num,
                        'total_amount' => iex_number_format($item->total_amount, 2, true)
                    ]
                ];
            }),
            ...$this->pagination
        ];
    }
}
