<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TaskMessageResponse extends ResourceCollection
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
            'next_page' => $resource->nextPageUrl(),
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
                        'user' => [
                            'id' => $item->user->id,
                        ],
                        'last_updated' => $item->created_at,
                        'order_id' => $item->task->id,
                        'public_id' => $item->task->public_id,
                        'display_id' => current_order_id($item->task),
                        'message' => $item->message,
                        'unread_count' => $item->unread_count,
                    ]
                ];
            }),
            ...$this->pagination
        ];
    }
}
