<?php
namespace iEXPackages\ExchangerClient\Http\Resources\Started;

use App\Models\Contact;
use App\Models\Menu;
use App\Models\Partner;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LastExchangesResources extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return Collection
     */
    public function toArray(Request $request): Collection
    {
        return $this->collection->map(function (Task $item) {

            $in_price = iex_number_format($item->give_price, $item->direction_exchange->currency1->number_format);
            $out_price = iex_number_format($item->receiving_price, $item->direction_exchange->currency2->number_format);

            $start = Carbon::parse($item->started_at);
            $lead_time = Carbon::parse($item->updated_at);

            $getSecond = $start->diffForHumans($lead_time, true, true);

            return  [
                'id' => $item->public_id,
                'attributes' => [
                    'image_in' => '/storage/payment_systems/'.$item->direction_exchange->currency1->payment->logo,
                    'image_out' => '/storage/payment_systems/'.$item->direction_exchange->currency2->payment->logo,
                    'in_code' => $item->direction_exchange->currency1->code_currency->name,
                    'out_code' => $item->direction_exchange->currency2->code_currency->name,
                    'in_price' => $in_price,
                    'out_price' => $out_price,
                    'income_amount' => [
                        'value' => $in_price,
                        'decimal' => $item->direction_exchange->currency1->number_format
                    ],

                    'outcome_amount' => [
                        'value' => $out_price,
                        'decimal' => $item->direction_exchange->currency2->number_format
                    ],

                    'updated_at' => Carbon::parse($item->created_at)->diffForHumans(),
                    'finished_at' => $getSecond,
                    'city' => $item->task_info?->city?->name
                        ?? $item->task_info?->city_name
                            ?? null
                ]
            ];
        });
    }
}
