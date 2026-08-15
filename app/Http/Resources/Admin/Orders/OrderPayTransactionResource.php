<?php

namespace App\Http\Resources\Admin\Orders;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderPayTransactionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'transaction_hash' => $this->pay_transaction_data->ext_data['transaction_hash'] ?? '',
            'link' => isset($this->direction_exchange->currency2->payment->explorer) ? str_replace('{hash}',
                $this->pay_transaction_data->ext_data['transaction_hash'] ?? '',
                $this->direction_exchange->currency2->payment->explorer->link
            ) : ''
        ];
    }
}
