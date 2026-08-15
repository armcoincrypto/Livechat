<?php
declare(strict_types=1);

namespace iEXPackages\ExchangerClient\Http\Resources\Account;

use App\Models\CodeCurrency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PartnerReferralsResources extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return Collection
     */
    public function toArray($request)
    {
        return $this->resource->map(function ($item) {
            return [
                'id' => $item->id,
                'type' => 'referral_charge',
                'attributes' => [
                    'status' => 'referral',
                    'created_at' => Carbon::parse($item->created_at)->diffForHumans(),
                    'amount' => $item->bonus_number,
                    'currency' => config('partners-bonus.name') ?? '',
                    'order_id' => iEXSetting('client_id_type_for_order') == 1 ? $item->tasks->public_id : $item->tasks->id,
                ],
            ];
        });
    }
}
