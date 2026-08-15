<?php

namespace iEXPackages\ExchangerApi\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CurrenciesResources extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $collections = $this->collection->mapWithKeys(function ($item, $key) {
            $paymentName = '';
            if (isset($item->payment)) {
                $paymentName = $item->payment->name;
            }

            $isoCode = '';
            if (isset($item->code_currency)) {
                $isoCode = $item->code_currency->name;
            }

            return [
                $item->id => [
                    'id' => $item->id,
                    'type' => 'currency',
                    'attributes' => [
                        'name' => ($item->visible_code_currency == 1) ? sprintf('%s %s', $paymentName, $isoCode) : $paymentName,
                        'currency_type' => isset($item->filter_currency) ? \Str::upper($item->filter_currency->name) : null,
                        'payment_name' => $paymentName,
                        'iso_code' => $isoCode,
                        'letter_cod' => $item->designation_xml,
                        'decimal' => $item->number_format,
                    ],
                ],
            ];
        });

        return [
            'data' => $collections,
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     */
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE);
    }
}
