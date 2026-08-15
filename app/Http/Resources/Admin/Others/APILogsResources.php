<?php

namespace App\Http\Resources\Admin\Others;

use Illuminate\Http\Request;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class APILogsResources extends ResourceCollection
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
                        'api_token'     => $item->api_token,
                        'api_action'    => $item->api_action,
                        'ip_address'    => $item->ip_address,
                        'headers'       => $item->headers,
                        'post_data'     => $item->post_data,
                        'status_code'   => $item->status_code,
                        'response_data' => $item->response_data,
                        'created_at'        => $item->created_at->format('c')
                    ],
                ];
            }),
            ...$this->pagination
        ];
    }
}

