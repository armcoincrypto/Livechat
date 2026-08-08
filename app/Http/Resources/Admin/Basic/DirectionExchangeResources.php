<?php
namespace App\Http\Resources\Admin\Basic;

use iEXPackages\Calculator\Calculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class DirectionExchangeResources extends ResourceCollection
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
                        'status' => (bool)$item->status,
                        'is_main' => $item->is_main,
                        'is_error_rate' => $item->is_error_rate,
                        'error_rate_text' => $item->error_rate_text,
                        'exchange_rate' => $item->exchange_rate,
                        'parser_source_name' => $item->parser_source_name,
                        'rate_source_label' => (static function () use ($item) {
                            $src = trim((string) ($item->parser_source_name ?? ''));
                            if ($src === '') {
                                return 'Не задан';
                            }
                            if (preg_match('/ручн|manual/iu', $src)) {
                                return 'Ручной';
                            }

                            return 'Автоматический ('.$src.')';
                        })(),
                        'oth_comm_percent' => $item->oth_comm_percent ?? 0,
                        'oth_comm_currency' => $item->oth_comm_currency ?? 0,
                        'oth_comm2_percent' => $item->oth_comm2_percent ?? 0,
                        'oth_comm2_currency' => $item->oth_comm2_currency ?? 0,
                        'profit' => $item->profit ?? 0,
                        'profit_s' => $item->profit_s ?? 0,
                        'ids_merchants' => $item->merchants->pluck('id'),
                        'ids_pays' => $item->gateway_payments->pluck('id'),
                        'tasks' => $item->tasks_count,

                        'commissions_by_cities' => optional($item->direction_exchange_cities)->map(function ($city) {
                                return [
                                    'city_id' => $city->id,
                                    'city_name' => $city->city?->name,
                                    'add_comm' => $city->add_comm,
                                    'profit' => $city->profit,
                                    'profit_s' => $city->profit_s,
                                ];
                            }) ?? [],

                        'currency_in' => [
                            'name' => $item->currency1->tech_name,
                            'icon' => '/storage/payment_systems/' . ($item->currency1->payment?->logo ?? '')
                        ],

                        'currency_out' => [
                            'name' => $item->currency2->tech_name,
                            'icon' => '/storage/payment_systems/' . ($item->currency2->payment?->logo ?? '')
                        ],

                        'min_price1' => $item->min_price1,
                        'min_price2' => $item->min_price2,
                        'max_price1' => $item->max_price1,
                        'max_price2' => $item->max_price2,
                        'is_manual_min_price1' => $item->is_manual_min_price1,
                        'is_manual_min_price2' => $item->is_manual_min_price2,
                        'is_manual_max_price1' => $item->is_manual_max_price1,
                        'is_manual_max_price2' => $item->is_manual_max_price2,

                        'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                        'updated_at_human' => $item->updated_at->diffForHumans(),
                    ]
                ];
            }),
            ...$this->pagination
        ];
    }
}
