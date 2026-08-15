<?php
namespace App\Http\Resources\Admin\Basic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class DirectionRequisiteResources extends ResourceCollection
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
                        'name' => $item->name,
                        'account_number' => $item->account_number,
                        'status' => (bool)$item->status,
                        'direction_exchanges' => $item->direction_exchange->count() > 0 ? $item->direction_exchange->map(function($item) {
                            return [
                                'id' => $item->id,
                                'name' => $item->tech_name
                            ];
                        }) : [],
                        'view' => $item->view,
                        'limit_day' => $item->limit_day,
                        'limit_month' => $item->limit_month
                    ]
                ];
            }),
            ...$this->pagination
        ];
    }
}
