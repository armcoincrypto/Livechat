<?php
namespace App\Http\Resources\Admin\Basic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class DirectionExchangeTrashedResources extends ResourceCollection
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
                        'tech_name' => $item->tech_name,
                        'tasks' => $item->tasks_count,
                        'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                        'updated_at_human' => $item->updated_at->diffForHumans(),
                    ]
                ];
            }),
            ...$this->pagination
        ];
    }
}
