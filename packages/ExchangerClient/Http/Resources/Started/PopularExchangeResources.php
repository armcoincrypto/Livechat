<?php
namespace iEXPackages\ExchangerClient\Http\Resources\Started;

use App\Models\Contact;
use App\Models\DirectionExchange;
use App\Models\Menu;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class PopularExchangeResources extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return Collection
     */
    public function toArray(Request $request): Collection
    {
        return $this->collection->map(function (DirectionExchange $item) {
            return  [
                'id' => $item->id,
                'attributes' => [
                    'image_in'      => $item->currency1?->payment?->logo ? '/storage/payment_systems/' . $item->currency1->payment->logo : null,
                    'image_out'     => $item->currency2?->payment?->logo ? '/storage/payment_systems/' . $item->currency2->payment->logo : null,
                    'in_code'       => $item->currency1?->code_currency?->name ?? null,
                    'out_code'      => $item->currency2?->code_currency?->name ?? null,
                    'name_in'       => ($item->currency1?->payment?->name && $item->currency1?->code_currency?->name)
                        ? ($item->currency1->payment->name . ' ' . $item->currency1->code_currency->name)
                        : null,
                    'name_out'      => ($item->currency2?->payment?->name && $item->currency2?->code_currency?->name)
                        ? ($item->currency2->payment->name . ' ' . $item->currency2->code_currency->name)
                        : null,
                    'code_in'       => $item->currency1?->designation_xml ?? null,
                    'code_out'      => $item->currency2?->designation_xml ?? null,
                    'orders_percent'=> (float) $item->orders_percent,
                    'orders_percent_delta' => (float) ($item->orders_percent_delta ?? 0),
                ]
            ];
        });
    }
}
