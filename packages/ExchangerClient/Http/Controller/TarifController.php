<?php

namespace iEXPackages\ExchangerClient\Http\Controller;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\DirectionExchange;
use Illuminate\Support\Carbon;

class TarifController extends Controller
{
    /**
     * Валюты с функцией кэширования
     *
     * @throws \Exception
     */
    public function currencies()
    {

        if ((int) iEXSetting('is_enable_app_cache', 0) == 1 and iEXSetting('enable_cache_tariffs')) {
            return cache()->remember('tariffs-cache', Carbon::now()->addHours(2), function () {
                return $this->currenciesNoCached();
            });
        }

        return $this->currenciesNoCached();
    }

    /**
     * Список курсов
     */
    public function currenciesNoCached(): array
    {
        $currency = Currency::whereHas('direction_exchange1', function ($q) {
            $q->where('status', '=', 1);
        })->where('status', '=', 0)->orderBy('sorting_tariffs')->cursor();
        $array = [];
        foreach ($currency as $item) {
            if ($item->visible_code_currency == 0) {
                $format_name = sprintf('%s %s', $item->payment->name, $item->code_currency->name);
            } else {
                $format_name = sprintf('%s', $item->payment->name);
            }

            $array[] = [
                'full_name' => $format_name,
                'color' => iEXSetting('is_gradient_text_color') ? $item->text_color : '#222332',

                'exchange' => $this->exchangers($item->id),
            ];
        }

        return $array;
    }

    protected function exchangers($id): array
    {
        $in = DirectionExchange::active()->with([
            'currency1' => function ($q) {
                $q->select('id', 'id_payment', 'id_code_currency', 'designation_xml');
            },
            'currency1.payment' => function ($q) {
                $q->select('id', 'name', 'logo');
            },
            'currency1.code_currency' => function ($q) {
                $q->select('id', 'name');
            },
            'currency1.reserve'])
            ->where('id_currency1', '=', $id)
            ->where('is_hidden_tariffs', '=', 0)->orderBy('sorting_tariffs');

        $response = $in->where('status', '=', 1)->get();

        $data = $response->filter(function ($filter) {
            if ($filter->is_enabled_exchange == 0) {
                return $filter;
            }

            // Отображаем направелния по расписанию
            if ($filter->is_enabled_exchange == 1 and
                Carbon::now()->format('H:i') > $filter->from_on_time and
                Carbon::now()->format('H:i') < $filter->to_on_time) {
                return $filter;
            }
        });

        $array = [];
        foreach ($data as $item) {
            [$in, $out] = explode(' = ', $item->exchange_rate);

            if ($item->currency1->visible_code_currency == 0) {
                $format_name_in = sprintf('%s %s',
                    $item->currency1->payment->name,
                    $item->currency1->code_currency->name
                );
            } else {
                $format_name_in = $item->currency1->payment->name;
            }

            if ($item->currency2->visible_code_currency == 0) {
                $format_name_out = sprintf('%s %s',
                    $item->currency2->payment->name,
                    $item->currency2->code_currency->name
                );
            } else {
                $format_name_out = $item->currency2->payment->name;
            }

            $array[] = [
                '_in' => [
                    'color' => iEXSetting('is_gradient_text_color') ? $item->currency1->text_color : '#222332',
                    'name' => $format_name_in,
                    'rate' => $in,
                    'code' => $item->currency1->designation_xml,
                    'icon_url_path' => '/storage/payment_systems/'.$item->currency1->payment->logo,
                ],
                '_out' => [
                    'color' => iEXSetting('is_gradient_text_color') ? $item->currency2->text_color : '#222332',
                    'name' => $format_name_out,
                    'code' => $item->currency2->designation_xml,
                    'rate' => $out,
                    'icon_url_path' => '/storage/payment_systems/'.$item->currency2->payment->logo,
                ],
                'status' => $item->status,
                'reserve' => isset($item->currency2->reserve) ? iex_reserve_amount($item->currency2->reserve) : 0,
                'p' => $item->currency1->sorting_1,
                'rate' => $item->course_value,
                'link' => "/?cur_from={$item->currency1->designation_xml}&cur_to={$item->currency2->designation_xml}",
            ];
        }

        return $array;
    }
}
