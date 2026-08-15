<?php

namespace App\Http\Resources\Admin\Users;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UserLogResources extends ResourceCollection
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
                        'user' => [
                            'name' => $item->user?->name,
                            'email' => $item->user?->email,
                        ],
                        'ip_address' => $item->ip,
                        'old_ip_address' => $item->old_ip_address,
                        'status' => $item->status,
                        'user_agent' => $item->user_agent,
                        'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                        'created_at_human' => $item->created_at->diffForHumans()
                    ],
                ];
            }),
            ...$this->pagination
        ];
    }
}
