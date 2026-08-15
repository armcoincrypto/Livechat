<?php

namespace iEXPackages\ExchangerApi\Http\Resources\AccountPartner;

use App\Models\CodeCurrency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;

class ReferralExchangesResources extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @return array
     */
    public function toArray(Request $request)
    {
        $response = $this->resource->map(function ($item) {
            return [
                'id' => $item->id,
                'type' => 'referral_charge',
                'attributes' => [
                    'status' => 'referral',
                    'created_at' => Carbon::parse($item->created_at)->diffForHumans(),
                    'amount' => $item->bonus_number,
                    'currency' => config('partners-bonus.name') ?? '',
                    'order_id' => current_order_id($item->tasks),
                ],
            ];
        });

        return [
            'data' => $response,
        ];
    }
}
