<?php

namespace App\Http\Resources\Admin\Basic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UserWalletsResources extends ResourceCollection
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
        $allBlackList = \App\Models\BlacklistOrder::pluck('value', 'value')->toArray();

        return [
            'data' => $this->collection->map(function ($item) use ($allBlackList)
            {
                $isExists = $allBlackList[$item->to_shot] ?? false;
                return [
                    'id' => $item->id,
                    'attributes' => [
                        'wallet' => $item->to_shot,
                        'name' => $item->direction_exchange->currency2->tech_name,
                        'is_blacklisted' => $isExists,
                        'user' => [
                            'id' => $item->user->id,
                            'name' => $item->user->name,
                            'email' => $item->user->email,
                        ],
                        'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                        'created_at_human' => $item->created_at->diffForHumans(),
                    ]
                ];
            }),
            ...$this->pagination
        ];
    }
}
