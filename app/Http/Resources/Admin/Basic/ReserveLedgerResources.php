<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Basic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ReserveLedgerResources extends ResourceCollection
{
    private array $pagination;

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

        // Necessary to remove meta and links
        $resource = $resource->getCollection();

        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($item) {
                $scale = 18;

                $norm = static function ($value) use ($scale): string {
                    if (function_exists('iex_money_normalize')) {
                        return iex_money_normalize($value, $scale);
                    }

                    return (string) ($value ?? '0');
                };

                $occurredAt = $item->occurred_at ?? $item->created_at;

                return [
                    'id' => (int) $item->id,
                    'attributes' => [
                        'reserve_id' => (int) ($item->reserve_id ?? 0),
                        'task_id' => isset($item->task_id) ? (int) $item->task_id : null,
                        'direction_exchange_id' => isset($item->direction_exchange_id) ? (int) $item->direction_exchange_id : null,
                        'currency_id' => isset($item->currency_id) ? (int) $item->currency_id : null,

                        'action' => (string) ($item->action ?? ''),
                        'source_type' => (string) ($item->source_type ?? ''),
                        'source_id' => (int) ($item->source_id ?? 0),

                        // суммы (строкой)
                        'delta' => $norm($item->delta ?? '0'),
                        'balance_before' => $norm($item->balance_before ?? '0'),
                        'balance_after' => $norm($item->balance_after ?? '0'),

                        // направление (если загружено)
                        'directionExchange' => $item->directionExchange ? [
                            'tech_name' => (string) ($item->directionExchange->tech_name ?? ''),
                        ] : null,

                        // meta (если есть)
                        'meta' => $item->meta ?? null,

                        // дата события
                        'occurred_at' => $occurredAt ? $occurredAt->toISOString() : null
                    ],
                ];
            }),
            ...$this->pagination,
        ];
    }
}
