<?php

namespace iEXPackages\ExchangerClient\Http\Resources\Orders;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OrdersUserResource extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return Collection
     */
    public function toArray(Request $request): Collection
    {
        return $this->resource->map(function ($item) {
            $public_id = $item->public_id;
            $success_message = null;
            if ($item->status == 4 and ! empty(iEXSetting('s_order_notify_text'))) {
                $success_message = iEXSetting('s_order_notify_text');
            }

            $in_icon_url = '/storage/payment_systems/'.$item->direction_exchange->currency1->payment->logo;
            $out_icon_url = '/storage/payment_systems/'.$item->direction_exchange->currency2->payment->logo;


            return [
                'id' => iEXSetting('client_id_type_for_order') == 1 ? $public_id : $item->id,
                'type' => 'order',
                'attributes' => [
                    'public_id' => $public_id,
                    'created_at' => Carbon::parse($item->created_at)->timestamp * 1000,
                    'status' => $item->status,
                    'status_string' => $item->task_status?->name,
                    'failed_text' => isset($item->tasks_rejection_status) ? $item->tasks_rejection_status->name : '',
                    'success_message' => $success_message,
                    'is_verification_card' => (int)$item->is_from_verification_card,
                    'is_from_identity_verification' => (int)$item->is_from_identity_verification,
                    'income_data' => [
                        'amount' => (float) $item->give_price,
                        'name' => currency_in($item, true),
                        'currency' => $item->direction_exchange->currency1->code_currency->name,
                        'account' => $item->from_shot,
                        'image' => $in_icon_url,
                        'number_format' => $item->direction_exchange->currency1->number_format,
                    ],
                    'outcome_data' => [
                        'amount' => (float) $item->receiving_price,
                        'name' => currency_out($item, true),
                        'currency' => $item->direction_exchange->currency2->code_currency->name,
                        'account' => $item->to_shot,
                        'image' => $out_icon_url,
                        'number_format' => $item->direction_exchange->currency2->number_format,
                    ],
                ],
            ];
        });
    }
}
