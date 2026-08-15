<?php

namespace iEXPackages\ExchangerApi\Http\Resources\Operations;

use App\Models\Currency;
use App\Services\Reserves\ReserveLinkResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class OperationsByIdResources extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $currencies = Currency::with(['payment', 'code_currency', 'filter_currency', 'reserve'])->where('status', '=', 0)
            ->get()->keyBy('id');

        $collections = $this->collection->mapWithKeys(function ($item, $key) use ($currencies) {
            $currencyOut = $currencies[$item['id_currency2']];

            $paymentName = '';
            if (isset($currencyOut['payment'])) {
                $paymentName = $currencyOut['payment']['name'];
            }

            $isoCode = '';
            if (isset($currencyOut['code_currency'])) {
                $isoCode = $currencyOut['code_currency']['name'];
            }

            $reserveModel = $currencyOut->reserve ?? null;

            if ($reserveModel) {
                /** @var ReserveLinkResolver $resolver */
                $resolver = app(ReserveLinkResolver::class);
                $reserv_value = $resolver->getEffectiveSumma($reserveModel, 18);
            } else {
                $reserv_value = '0';
            }

            return [
                $item['id'] => [
                    'id' => $item['id'],
                    'type' => 'direction_out',
                    'attributes' => [
                        'out_currency' => [
                            'id' => $item['id_currency2'],
                            'name' => ($currencyOut['visible_code_currency'] == 1) ? sprintf('%s %s', $paymentName, $isoCode) : $paymentName,
                            'currency_type' => isset($currencyOut['filter_currency']) ? \Str::upper($currencyOut['filter_currency']['name']) : null,
                            'payment_name' => $paymentName,
                            'iso_code' => $isoCode,
                            'letter_cod' => $currencyOut['designation_xml'],
                            'decimal' => $currencyOut['number_format'],
                        ],

                        'reserv_value' => $reserv_value,
                        'course' => $item['course'],
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
