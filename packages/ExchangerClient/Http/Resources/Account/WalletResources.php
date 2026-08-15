<?php
declare(strict_types=1);

namespace iEXPackages\ExchangerClient\Http\Resources\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class WalletResources extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return Collection
     */
    public function toArray(Request $request): Collection
    {
        return $this->collection->mapWithKeys(function ($item)
        {
            return [
                $item->id => [
                    'id' => $item->id,
                    'attributes' => [
                        'currency_id' => $item->id_currency,
                        'wallet' => $item->wallet,
                    ]
                ]
            ];
        });
    }
}
