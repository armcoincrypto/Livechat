<?php

namespace App\Http\Resources\Admin\Orders;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderMerchantTransactionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'transaction_hash' => $this->merchant_transaction_hash->transaction_hash,
            'link' => isset($this->direction_exchange->currency1->payment->explorer) ? str_replace('{hash}',
                $this->merchant_transaction_hash->transaction_hash,
                $this->direction_exchange->currency1->payment->explorer->link
            ) : ''
        ];
    }
}
