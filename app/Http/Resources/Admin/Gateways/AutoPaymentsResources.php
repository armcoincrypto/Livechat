<?php

namespace App\Http\Resources\Admin\Gateways;

use Illuminate\Http\Request;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class AutoPaymentsResources extends ResourceCollection
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
                        'status' => (bool)$item->status,
                        'is_config_done' => $item->is_config_done,
                        'alias' => $item->alias,
                        'allow_ip_address' => $item->allow_ip_address,
                        'security_hash' => $item->security_hash,
                        'locales' => $item->getTranslations(),
                        'volume_to_usd' => '$'.iex_number_format((float) $item->volume_to_usd, 2),
                        'last_order_id' => $item->last_order_id,
                        'order_count' => $item->order_count,
                        'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                        'created_at_human' => $item->created_at->diffForHumans(),
                        'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                        'updated_at_human' => $item->updated_at->diffForHumans(),
                    ],
                ];
            }),
            ...$this->pagination
        ];
    }
}
