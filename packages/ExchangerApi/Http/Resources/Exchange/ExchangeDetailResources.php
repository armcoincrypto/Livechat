<?php

namespace iEXPackages\ExchangerApi\Http\Resources\Exchange;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;

class ExchangeDetailResources extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        return $this->resource->map(function ($item) {
            $inPaymentName = '';
            if (isset($item->direction_exchange->currency1) and isset($item->direction_exchange->currency1->payment)) {
                $inPaymentName = $item->direction_exchange->currency1->payment->name;
            }

            $inIsoCode = '';
            if (isset($item->direction_exchange->currency1) and isset($item->direction_exchange->currency1->code_currency)) {
                $inIsoCode = $item->direction_exchange->currency1->code_currency->name;
            }

            $outPaymentName = '';
            if (isset($item->direction_exchange->currency2) and isset($item->direction_exchange->currency2->payment)) {
                $outPaymentName = $item->direction_exchange->currency2->payment->name;
            }

            $outIsoCode = '';
            if (isset($item->direction_exchange->currency1) and isset($item->direction_exchange->currency2->code_currency)) {
                $outIsoCode = $item->direction_exchange->currency2->code_currency->name;
            }

            $public_id = $item->public_id;
            $success_message = null;
            if ($item->status == 4 and ! empty(iEXSetting('s_order_notify_text'))) {
                $success_message = iEXSetting('s_order_notify_text');
            }

            return [
                'id' => iEXSetting('client_id_type_for_order') == 1 ? $public_id : $item->id,
                'type' => 'order',
                'attributes' => [
                    'email' => $item->email,
                    'public_id' => $public_id,
                    'created_at' => Carbon::parse($item->created_at)->translatedFormat('d M Y, H:i'),
                    'status' => $item->status,
                    'failed_text' => isset($item->tasks_rejection_status) ? $item->tasks_rejection_status->name : '',
                    'success_message' => $success_message,
                    'exchange_rate' => $item->course_float,

                    'in_currency' => [
                        'amount' => (float) $item->give_price,
                        'name' => ($item->direction_exchange->currency1->visible_code_currency == 0) ? sprintf('%s %s', $inPaymentName, $inIsoCode) : $inPaymentName,
                        'currency' => $item->direction_exchange->currency1->code_currency->name,
                        'decimal' => $item->direction_exchange->currency1->number_format,
                        'wallet' => $item->from_shot,
                        'number_format' => $item->direction_exchange->currency1->number_format,
                    ],

                    'out_currency' => [
                        'amount' => (float) $item->receiving_price,
                        'name' => ($item->direction_exchange->currency2->visible_code_currency == 0) ? sprintf('%s %s', $outPaymentName, $outIsoCode) : $outPaymentName,
                        'currency' => $item->direction_exchange->currency2->code_currency->name,
                        'wallet' => $item->to_shot,
                        'decimal' => $item->direction_exchange->currency2->number_format,
                    ],
                ],
            ];
        });
    }
}
