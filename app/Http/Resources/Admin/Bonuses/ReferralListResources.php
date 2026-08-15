<?php

namespace App\Http\Resources\Admin\Bonuses;


use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ReferralListResources extends ResourceCollection
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
                    'stats' => [
                        'tasks_total_count'     => $item->tasks_total_count,
                        'tasks_completed_count' => $item->tasks_completed_count,
                        'has_completed'         => $item->tasks_completed_count > 0,
                    ],

                    'id' => $item->id,
                    'attributes' => [
                        'user' => isset($item->user) ? [
                            'id' => $item->user->id,
                            'attributes' => [
                                'name' => $item->user->name,
                                'email' => $item->user->email,
                            ]
                        ] : [],

                        'referral' => isset($item->referral_link) ? [
                            'id' => $item->referral_link->user->id,
                            'attributes' => [
                                'name' => $item->referral_link->user->name,
                                'email' => $item->referral_link->user->email,
                            ]
                        ] : [],
                        'created_at' => $item->created_at->format('c'),
                        'updated_at' => $item->updated_at->format('c')
                    ]
                ];
            }),
            ...$this->pagination
        ];
    }
}
