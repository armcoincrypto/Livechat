<?php

namespace App\Http\Resources\Admin\Basic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ReserveManualResources extends ResourceCollection
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
                        'id_task' => $item->id_task,
                        'direction' => isset($item->tasks) ? [
                            'id' => $item->tasks->id_direction_exchange,
                            'name' => $item->tasks->direction_exchange->tech_name
                        ] : [],
                        'type_reserve' => $item->type_reserve,
                        'amount' => $item->amount,
                        'amount_from' => $item->amount_from,
                        'amount_to' => $item->amount_to,

                        'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                        'created_at_human' => $item->created_at->diffForHumans(),
                    ]
                ];
            }),
            ...$this->pagination
        ];
    }
}
