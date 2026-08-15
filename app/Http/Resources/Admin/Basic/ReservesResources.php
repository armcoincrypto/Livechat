<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Basic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ReservesResources extends ResourceCollection
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

        $resource = $resource->getCollection();

        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($reserve) {
                $norm = static function ($value): string {
                    if (function_exists('iex_money_normalize')) {
                        return iex_money_normalize($value);
                    }
                    return (string) ($value ?? '0');
                };

                $currency = $reserve->currency;
                $payment = $currency?->payment;
                $code = $currency?->code_currency;

                $link = $reserve->link ?? null;
                $parentId = $link?->parent_reserve_id ?? null;

                // ВАЖНО: дерево теперь формирует контроллер и кладёт в атрибут
                $childrenTree = $reserve->getAttribute('children_tree') ?? [];
                $childrenTotal = (int) ($reserve->getAttribute('children_total_count') ?? 0);

                return [
                    'id' => (int) $reserve->id,
                    'attributes' => [
                        'currency' => [
                            'id' => $currency?->id,
                            'name' => $currency?->tech_name,
                            'code_currency' => [
                                'id' => $code?->id,
                                'name' => $code?->name,
                            ],
                            'payment' => [
                                'id' => $payment?->id,
                                'name' => $payment?->name,
                                'logo' => $payment?->logo ? '/storage/payment_systems/' . $payment->logo : null,
                            ],
                        ],

                        'summa' => $norm($reserve->summa ?? '0'),
                        'is_fixed_reserve' => (int) ($reserve->is_fixed_reserve ?? 0),

                        'link' => [
                            'parent_reserve_id' => $parentId !== null ? (int) $parentId : null,
                            'is_active' => $link ? (bool) ($link->is_active ?? true) : true,
                            'note' => $link?->note,
                        ],
                        'is_linked' => $parentId !== null,
                        'is_root' => $parentId === null,

                        // ✅ теперь это общее кол-во потомков по всей цепочке, а не только прямые дети
                        'children_total_count' => $childrenTotal,

                        // ✅ дерево всех уровней
                        'children_tree' => $childrenTree,

                        'created_at' => $reserve->created_at?->toISOString(),
                        'updated_at' => $reserve->updated_at?->toISOString(),
                    ],
                ];
            }),
            ...$this->pagination,
        ];
    }
}
