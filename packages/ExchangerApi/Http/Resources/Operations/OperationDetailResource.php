<?php

namespace iEXPackages\ExchangerApi\Http\Resources\Operations;

use App\Services\Rates\RateDirectionEligibility;
use App\Models\DirectionExchangeCity;
use iEXPackages\Calculator\CalculatorFacade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Throwable;

class OperationDetailResource extends JsonResource
{
    /**
     * Массив данных
     */
    public function toArray(Request $request): array
    {
        $inPaymentName = '';
        if (isset($this->currency1) and isset($this->currency1->payment)) {
            $inPaymentName = $this->currency1->payment->name;
        }

        $inIsoCode = '';
        if (isset($this->currency1) and isset($this->currency1->code_currency)) {
            $inIsoCode = $this->currency1->code_currency->name;
        }

        $outPaymentName = '';
        if (isset($this->currency2) and isset($this->currency2->payment)) {
            $outPaymentName = $this->currency2->payment->name;
        }

        $outIsoCode = '';
        if (isset($this->currency1) and isset($this->currency2->code_currency)) {
            $outIsoCode = $this->currency2->code_currency->name;
        }

        // Получаем список городов
        $all_cities = DirectionExchangeCity::pluck('id_direction_exchange', 'id_direction_exchange')->toArray();

        // Получаем города и страцы
        $cities = [];
        if (isset($all_cities[$this->id]))
        {
            $direction_countries = [];
            $direction_cities = [];
            $direction_country_default = 0;
            $group_countries = DirectionExchangeCity::with('geo_country')
                ->where('id_direction_exchange', $this->id)->groupBy('id_country')
                ->get()->reject(function ($item) {
                    return $item->id_country == 0;
                });

            $country_options = [];
            foreach ($group_countries as $country) {
                $country_options[] = [
                    'value' => $country->id_country,
                    'label' => isset($country->geo_country) ? $country->geo_country->value : '',
                ];
            }

            if (count($country_options) > 0) {
                $direction_country_default = $country_options[0]['value'];

                $direction_countries[] = [
                    'key' => 'country_id',
                    'values' => $country_options,
                ];

                $cities_list = $this->direction_exchange_cities->reject(function ($value) {
                    return $value->id_country == 0;
                });

                foreach ($cities_list as $city) {
                    $direction_cities[] = [
                        'id_country' => $city->id_country,
                        'value' => $city->id,
                        'label' => $city->city->name,
                    ];
                }
            }

            $cities['country_default'] = $direction_country_default;
            $cities['countries'] = $direction_countries;
            $cities['list'] = $direction_cities;
        }

        $calculatorResult = null;
        $rateUnavailable = false;
        $rateUnavailableCode = null;
        try {
            $surface = RateDirectionEligibility::make()->evaluateDirection($this->resource);
            if (!empty($surface['quote_allowed'])) {
                $calculator = CalculatorFacade::setDirectionExchange($this->resource)->calculate();
                $calculatorResult = $calculator->toArray();
            } else {
                $rateUnavailable = true;
                $rateUnavailableCode = RateDirectionEligibility::ERROR_DIRECTION_TEMPORARILY_UNAVAILABLE;
            }
        } catch (Throwable $e) {
            Log::error('rate_public_surface_api_quote_gate_failed', [
                'direction_id' => $this->id ?? null,
                'message' => $e->getMessage(),
            ]);
            $rateUnavailable = true;
            $rateUnavailableCode = RateDirectionEligibility::ERROR_DIRECTION_TEMPORARILY_UNAVAILABLE;
        }

        $response = [
            'id' => $this->id,
            'type' => 'direction',
            'attributes' => [
                'position_num_in' => $this->sorting_1,

                'in_currency' => [
                    'id' => $this->id_currency1,
                    'letter_cod' => $this->currency1->designation_xml,
                    'name' => ($this->currency1->visible_code_currency == 1) ? sprintf('%s %s', $inPaymentName, $inIsoCode) : $inPaymentName,
                    'label_in' => $this->currency1->field_name_from,
                    'label_out' => $this->currency1->field_name_to,
                    'error_message' => $this->currency1->min_max_error_message,
                    'decimal' => $this->currency1->number_format,
                    'fields_in' => new FormFieldsResource($this->currency1->currency_in_fields->where('status', '=', 0)->sortBy('sorting')),
                    'fields_out' => new FormFieldsResource($this->currency1->currency_out_fields->where('status', '=', 0)->sortBy('sorting')),
                ],

                'position_num_out' => $this->sorting_2,
                'out_currency' => [
                    'id' => $this->id_currency2,
                    'letter_cod' => $this->currency2->designation_xml,
                    'name' => ($this->currency2->visible_code_currency == 1) ? sprintf('%s %s', $outPaymentName, $outIsoCode) : $outPaymentName,
                    'label_in' => $this->currency2->field_name_from,
                    'label_out' => $this->currency2->field_name_to,
                    'error_message' => $this->currency2->min_max_error_message,
                    'decimal' => $this->currency2->number_format,
                    'fields_in' => new FormFieldsResource($this->currency2->currency_in_fields->where('status', '=', 0)->sortBy('sorting')),
                    'fields_out' => new FormFieldsResource($this->currency2->currency_out_fields->where('status', '=', 0)->sortBy('sorting')),

                ],
                'course' => $calculatorResult,
                'rate_unavailable' => $rateUnavailable,
                'error_code' => $rateUnavailableCode,
                'min_in' => $this->min_price1,
                'max_in' => $this->max_price1,
                'min_out' => $this->min_price2,
                'max_out' => $this->max_price2,
                'cities' => $cities,
            ],
        ];

        $direction_fields = [];
        if (isset($this->direction_field_sorting) and count($this->direction_field_sorting) > 0) {
            foreach ($this->direction_field_sorting as $relationship) {
                $direction_fields[] = [
                    'key' => $relationship->key_id,
                    'options' => [
                        'label' => $relationship->name,
                        'placeholder' => $relationship->name,
                        'required' => (bool) $relationship->obligatory_field == 0,
                    ],
                ];
            }
        }

        $response['attributes']['fields'] = $direction_fields;

        return $response;
    }

    /**
     * Customize the outgoing response for the resource.
     */
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE);
    }
}
