<?php
declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReviewsResponses extends ResourceCollection
{
    /**
     * Преобразуйте коллекцию ресурсов в массив.
     */
    public function toArray(Request $request): Collection
    {
        return $this->collection->map(function ($item)
        {
            return [
                'id' => $item->id,
                'attributes' => [
                    'created_at' => Carbon::parse($item->created_at)->translatedFormat('d M Y H:i'),
                    'text' => $item->text,
                    'rate_speed' => $item->rate_speed,
                    'name' => $item->name,
                    'income_payment' => [
                        'amount' => $item['tasks']['give_price'],
                        'decimal' => $item['tasks']['direction_exchange']['currency1']['number_format'],
                        'currency' => $item['tasks']['direction_exchange']['currency1']['payment']['name'].' '.$item['tasks']['direction_exchange']['currency1']['code_currency']['name'],
                        'logo' => '/storage/payment_systems/'.$item['tasks']['direction_exchange']['currency1']['payment']['logo'],
                    ],
                    'outcome_payment' => [
                        'amount' => $item['tasks']['receiving_price'],
                        'decimal' => $item['tasks']['direction_exchange']['currency2']['number_format'],
                        'currency' => $item['tasks']['direction_exchange']['currency2']['payment']['name'].' '.$item['tasks']['direction_exchange']['currency2']['code_currency']['name'],
                        'logo' => '/storage/payment_systems/'.$item['tasks']['direction_exchange']['currency2']['payment']['logo'],
                    ],
                ],
            ];
        });
    }
}
