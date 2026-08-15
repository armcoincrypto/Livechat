<?php

namespace App\Http\Resources\Admin\Bonuses;


use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ReferralExchangesResources extends ResourceCollection
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
                    'id' => $item->id,
                    'attributes' => [
                        'user' => isset($item->user) ? [
                            'id' => $item->user->id,
                            'attributes' => [
                                'name' => $item->user->name,
                                'email' => $item->user->email,
                            ]
                        ] : [],

                        'partner' => isset($item->user_admin) ? [
                            'id' => $item->user_admin->id,
                            'attributes' => [
                                'name' => $item->user_admin->name,
                                'email' => $item->user_admin->email,
                            ]
                        ] : [],

                        'id_task' => $item->id_task,
                        'text' => $item->text,
                        'bonus' => $item->bonus,
                        'current_percent' => $item->current_percent,

                        'created_at' => $item->created_at->format('c'),
                        'updated_at' => $item->updated_at->format('c'),
                    ]
                ];
            }),
            ...$this->pagination
        ];
    }
}
