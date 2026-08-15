<?php

namespace iEXPackages\ExchangerClient\Http\Resources\Account;

use Illuminate\Http\Resources\Json\ResourceCollection;

class WalletCurrencyResources extends ResourceCollection
{
    public function toArray($request)
    {
        return $this->collection->mapWithKeys(function ($item) {
            return  [
                $item->id => [
                    'id' => $item->id,
                    'attributes' => [
                        'icon_url' => sprintf('%s/%s/%s', config('app.api_url'), config('image.folders.payment_systems'), $item?->payment->logo),
                        'name' => $item->payment->name.' '.$item->code_currency->name
                    ]
                ]
            ];
        });
    }
}
