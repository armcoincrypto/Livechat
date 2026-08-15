<?php

namespace App\Http\Resources\Admin\Gateways;

use Illuminate\Http\Request;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class MerchantsResources extends ResourceCollection
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

                        'health' => $item->healthStatus ? [
                            'status'      => (string) ($item->healthStatus->status ?? ''),
                            'http_status' => $item->healthStatus->http_status,
                            'latency_ms'  => $item->healthStatus->latency_ms,
                            'message'     => (string) ($item->healthStatus->message ?? ''),
                            'checked_at'  => $item->healthStatus->checked_at?->toIso8601String(),
                            'last_ok_at'  => $item->healthStatus->last_ok_at?->toIso8601String(),
                            'fail_streak' => (int) ($item->healthStatus->fail_streak ?? 0),
                        ] : null,

                        'locales' => $item->getTranslations(),
                        'currencies' => $item->currencies,
                        'direction_exchange' => $item->direction_exchange,
                        'order_num' => $item->order_num,
                        'total_usd' => '$'.iex_number_format($item->total_usd ?? 0, 2),
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
