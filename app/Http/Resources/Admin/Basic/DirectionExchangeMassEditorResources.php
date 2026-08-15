<?php
namespace App\Http\Resources\Admin\Basic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class DirectionExchangeMassEditorResources extends ResourceCollection
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
                        'deadline'  =>  $item->getTranslations('deadline'),
                        'instructions' => $item->getTranslations('instructions'),
                        'desc_exchange' => $item->getTranslations('desc_exchange'),
                        'desc_exchange_dop' => $item->getTranslations('desc_exchange_dop'),
                        'formalization_text' => $item->getTranslations('formalization_text'),
                        'other_docs' => $item->getTranslations('other_docs'),
                        'notice_process_desc' => $item->getTranslations('notice_process_desc'),
                        'text_order_success' => $item->getTranslations('text_order_success'),
                        'text_order_failed' => $item->getTranslations('text_order_failed'),
                        'text_order_confirm' => $item->getTranslations('text_order_confirm'),
                        'order_button_i_pay'  =>  $item->getTranslations('order_button_i_pay'),
                        'order_button_i_pay_text'  =>  $item->getTranslations('order_button_i_pay_text'),
                        'order_button_i_confirm'  =>  $item->getTranslations('order_button_i_confirm'),

                        'profit' => $item->profit ?? 0,
                        'profit_s' => $item->profit_s ?? 0,
                        'profit_partner' => $item->profit_partner ?? 0,
                        'profit_partner_s' => $item->profit_partner_s ?? 0,

                        'text_order_created_email' => $item->getTranslations('text_order_created_email'),
                        'min_price1' => $item->min_price1 ?? 0,
                        'min_price2' => $item->min_price2 ?? 0,
                        'max_price1' => $item->max_price1 ?? 0,
                        'max_price2' => $item->max_price2 ?? 0,
                        'is_manual_min_price1' => $item->is_manual_min_price1 ?? 0,
                        'is_manual_min_price2' => $item->is_manual_min_price2 ?? 0,
                        'is_manual_max_price1' => $item->is_manual_max_price1 ?? 0,
                        'is_manual_max_price2' => $item->is_manual_max_price2 ?? 0,

                        'oth_comm_percent' => $item->oth_comm_percent ?? 0,
                        'oth_comm_currency' => $item->oth_comm_currency ?? 0,
                        'oth_comm2_percent' => $item->oth_comm2_percent ?? 0,
                        'oth_comm2_currency' => $item->oth_comm2_currency ?? 0,

                        'pay_comm_percent' => $item->pay_comm_percent ?? 0,
                        'pay_comm_currency' => $item->pay_comm_currency ?? 0,
                        'pay_comm2_percent' => $item->pay_comm2_percent ?? 0,
                        'pay_comm2_currency' => $item->pay_comm2_currency ?? 0,
                    ]
                ];
            }),
            ...$this->pagination
        ];
    }
}
