<?php

namespace App\Http\Resources\Admin\Basic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class RequisitesResources extends ResourceCollection
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
                        'limit_views' => $item->limit_views,
                        'view' => $item->view,
                        'account_number' => $item->account_number,
                        'is_already_used' => $item->is_already_used,
                        'is_unique_shot' => $item->is_unique_shot,
                        'name' => $item->name,
                        'currency' => [
                            'id' => $item->currency->id,
                            'name' => $item->currency->tech_name,

                            'logo' => $item->currency?->payment?->logo,
                            'logo_url' => (isset($item->currency?->payment) and !empty($item->currency?->payment->logo)) ? '/storage/payment_systems/' . $item->currency?->payment->logo : '',
                        ],
                        'total_orders' => $item->total_orders,

                        'method_request_payment' => (int)$item->currency?->method_request_payment,
                        'group' => [
                            'id' => $item->group?->id,
                            'name' => $item->group?->name
                        ],
                        'limit_day' => $item->limit_day,
                        'limit_month' => $item->limit_month,

                        'total_summa_day' => (float)$item->total_summa_day,
                        'total_summa_month' => (float)$item->total_summa_month,
                        'status' => (bool)$item->status,

                        'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                        'created_at_human' => $item->created_at->diffForHumans(),
                    ]
                ];
            }),
            ...$this->pagination
        ];
    }
}
