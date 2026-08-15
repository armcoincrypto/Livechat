<?php

namespace App\Http\Resources\Admin\Bonuses;


use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ReferralLogsResources extends ResourceCollection
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
                        'referral' => isset($item->referral) ? [
                            'id' => $item->referral->id,
                            'attributes' => [
                                'name' => $item->referral->name,
                                'email' => $item->referral->email,
                            ]
                        ] : [],

                        'user' => isset($item->user) ? [
                            'id' => $item->user?->id,
                            'attributes' => [
                                'name' => $item->user?->name,
                                'email' => $item->user?->email,
                            ]
                        ] : [],

                        'text' => $item->text,
                        'type' => $item->type,
                        'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                        'created_at_human' => $item->created_at->diffForHumans()
                    ]
                ];
            }),
            ...$this->pagination
        ];
    }
}
