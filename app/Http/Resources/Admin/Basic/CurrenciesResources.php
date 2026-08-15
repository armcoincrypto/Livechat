<?php
declare(strict_types=1);

namespace App\Http\Resources\Admin\Basic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Services\Reserves\ReserveLinkResolver;

class CurrenciesResources extends ResourceCollection
{
    private array $pagination = [];

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
                $reserveValue = '0';
                $isReserveValue = false;

                $reserveModel = $item->reserve ?? null;

                if ($reserveModel) {
                    /** @var ReserveLinkResolver $resolver */
                    $resolver = app(ReserveLinkResolver::class);

                    // Эффективный резерв (корень цепочки), строкой без scientific notation
                    $effective = $resolver->getEffectiveSumma($reserveModel, 18);

                    // Не считаем отрицательные значения как доступный резерв
                    // (по UI можно решать отдельно)
                    $effectiveFloat = (float) $effective;

                    if ($effectiveFloat >= 0) {
                        $reserveValue = iex_number_format($effectiveFloat, (int) $item->number_format, true, true);
                        $isReserveValue = $effectiveFloat > 0;
                    }
                }

                return [
                    'id' => $item->id,
                    'attributes' => [
                        'tech_name' => $item->tech_name,
                        'payment_name' => isset($item->payment) ? $item->payment->name : '',
                        'payment_logo' => $item->payment?->logo,
                        'payment_logo_url' => (isset($item->payment) and !empty($item->payment->logo)) ? '/storage/payment_systems/' . $item->payment->logo : '',
                        'id_group_network' => $item->id_group_network,
                        'group_network_name' => isset($item->currency_group_network) ? $item->currency_group_network->title : '',
                        'code_name' => isset($item->code_currency) ? $item->code_currency->name : '',
                        'designation_xml' => $item->designation_xml,
                        'ids_merchants' => $item->merchants->pluck('id'),
                        'ids_pays' => $item->gateway_payments->pluck('id'),
                        'isReserveValue' => $isReserveValue,
                        'reserve' => (string)$reserveValue,
                        'receiving_amount' => isset($item->currency_analytics) ? iex_number_format((float)$item->currency_analytics->in_amount, $item->number_format, true) : 0,
                        'sending_amount' => isset($item->currency_analytics) ? iex_number_format((float)$item->currency_analytics->out_amount, $item->number_format, true) : 0,
                        'number_format' => $item->number_format,
                        'status' => $item->status,
                        'direction_in' => $item->direction_exchange_in_count,
                        'direction_out' => $item->direction_exchange_out_count,
                        'updated_at' => $item->updated_at?->toISOString(),
                    ]
                ];
            }),
            ...$this->pagination
        ];
    }
}
