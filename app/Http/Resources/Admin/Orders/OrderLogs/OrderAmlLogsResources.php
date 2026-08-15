<?php

namespace App\Http\Resources\Admin\Orders\OrderLogs;


use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class OrderAmlLogsResources extends ResourceCollection
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

        $resource = $resource->getCollection();

        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($item)
            {
                $ext_params = [];
                if(isset($item->ext_params['all_risks'])) {
                    $ext_params = collect($item->ext_params['all_risks'])
                        ->only('risk_score', 'other_risks', 'transaction', 'with_address');
                }


                return [
                    'id' => $item->id,
                    'attributes' => [
                        'method' => $item->method,
                        'alias' => $item->alias,
                        'id_task' => $item->id_task,
                        'risk_score' => ($item->ext_params['risk_score'] ?? 0),
                        'all_risks' => $ext_params,
                        'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                        'created_at_human' => $item->created_at->diffForHumans(),
                    ]
                ];
            }),
            ...$this->pagination
        ];
    }
}
