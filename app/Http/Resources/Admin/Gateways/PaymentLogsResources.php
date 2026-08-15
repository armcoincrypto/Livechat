<?php

namespace App\Http\Resources\Admin\Gateways;


use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PaymentLogsResources extends ResourceCollection
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
                        'id_order' => $item->id_task,
                        'provider' => $item->provider,
                        'url' => $item->url,
                        'headers' => $item->headers,
                        'content' => $item->content,
                        'response' => $item->response,
                        'created_at' => Carbon::parse($item->created_at)->format('c'),
                        'created_at_human' => $item->created_at->diffForHumans(),
                    ]
                ];
            }),
            ...$this->pagination
        ];
    }
}
