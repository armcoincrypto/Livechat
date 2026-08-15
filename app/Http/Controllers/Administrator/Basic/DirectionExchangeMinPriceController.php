<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Models\DirectionExchange;
use App\Models\DirectionExchangeMinPriceLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DirectionExchangeMinPriceController extends Controller
{
    private array $logErrors = [];

    /**
     * Групповая корректировка минимальных и макс. цен
     *
     * @throws \Exception
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $exchange1 = DirectionExchange::select('id', 'status', 'id_currency1', 'is_manual_min_price1', 'is_manual_max_price1', 'is_manual_min_price2', 'is_manual_max_price2', 'min_price1', 'min_price2', 'max_price1', 'max_price2', 'updated_at')
            ->with(['currency1' => function ($q) {
                $q->select('id', 'status', 'id_payment', 'id_code_currency');
            }, 'currency1.payment' => function ($q) {
                $q->select('id', 'name');
            }, 'currency1.code_currency' => function ($q) {
                $q->select('id', 'name');
            }])->where('status', '=', 1)->whereHas('currency1', function ($query) {
                return $query->where('status', '=', 0);
            })->where([
                ['is_manual_min_price1', '=', 0], ['is_manual_max_price1', '=', 0],
            ])->groupBy('id_currency1')->get();

        $exchange2 = DirectionExchange::select('id', 'status', 'id_currency2', 'is_manual_min_price1', 'is_manual_max_price1', 'is_manual_min_price2', 'is_manual_max_price2', 'min_price1', 'min_price2', 'max_price1', 'max_price2', 'updated_at')
            ->with(['currency2' => function ($q) {
                $q->select('id', 'status', 'id_payment', 'id_code_currency');
            }, 'currency2.payment' => function ($q) {
                $q->select('id', 'name');
            }, 'currency2.code_currency' => function ($q) {
                $q->select('id', 'name');
            }])->where('status', '=', 1)->whereHas('currency1', function ($query) {
                return $query->where('status', '=', 0);
            })->where([
                ['is_manual_min_price2', '=', 0], ['is_manual_max_price2', '=', 0],
            ])->groupBy('id_currency2')->cursor();



        $logs = DirectionExchangeMinPriceLog::with(['direction_exchange' => function($q) {
            $q->select('id', 'tech_name');
        }])->orderBy('id', 'desc')->limit(50)->get();

        return response()->json([
            'exchange1' => $exchange1->map(function ($item) {
                return [
                    'id' => $item->currency1->id,
                    'value' => $item->currency1->payment->name. ' '. $item->currency1->code_currency->name,
                    'min_price1' => $item->min_price1,
                    'max_price1' => $item->max_price1
                ];
            })->values(),

            'exchange2' => $exchange2->map(function ($item) {
                return [
                    'id' => $item->currency2->id,
                    'value' => $item->currency2->payment->name. ' '. $item->currency2->code_currency->name,
                    'min_price2' => $item->min_price2,
                    'max_price2' => $item->max_price2
                ];
            })->values(),

            'logs' => $logs->map(function($item) {
              return [
                  'id' => $item->id,
                  'direction_name' => $item->direction_exchange->tech_name,
                  'id_direction' => $item->id_direction_exchange,
                  'description' => $item->description
              ];
            })
        ]);
    }

    public function store()
    {
        // Обновление мин. сумм
        DirectionExchange::with(['currency1' => function ($q) {
            $q->select('id', 'number_format');
        }])->select('id', 'status', 'id_currency1', 'is_manual_min_price1', 'min_price2', 'course_value', 'tech_name', 'min_price1')->where([
                ['status', '=', 1],
                ['is_manual_min_price1', '=', 0]]
        )->chunk(100, function ($exchanges) {

            foreach ($exchanges as $exchange) {
                try {
                    $exchange->update([
                        'min_price1' => iex_number_format($exchange->min_price2 / $exchange->course_value, $exchange->currency1->number_format),
                    ]);

                } catch (\Error $exception) {
                    $this->logErrors[] = [
                        'created_at' => Carbon::now()->toDateTimeString(),
                        'updated_at' => Carbon::now()->toDateTimeString(),
                        'id_direction_exchange' => $exchange->id,
                        'direction_name' => $exchange->tech_name,
                        'description' => $exception->getMessage(),
                        'exchange_rate' => $exchange->course_value,
                    ];
                }
            }
        });

        DirectionExchange::with(['currency1' => function ($q) {
            $q->select('id', 'number_format');
        }])->select('id', 'status', 'id_currency1', 'is_manual_max_price1', 'max_price2', 'course_value', 'tech_name', 'max_price1')->where([
                ['status', '=', 1],
                ['is_manual_max_price1', '=', 0]]
        )->chunk(100, function ($exchanges) {

            foreach ($exchanges as $exchange) {
                try {
                    $exchange->update([
                        'max_price1' => iex_number_format($exchange->max_price2 / $exchange->course_value, $exchange->currency1->number_format),
                    ]);
                } catch (\Error $exception) {
                    $this->logErrors[] = [
                        'created_at' => Carbon::now()->toDateTimeString(),
                        'updated_at' => Carbon::now()->toDateTimeString(),
                        'id_direction_exchange' => $exchange->id,
                        'direction_name' => $exchange->tech_name,
                        'description' => $exception->getMessage(),
                        'exchange_rate' => $exchange->course_value,
                    ];
                }
            }
        });

        \DB::table('direction_exchange_min_price_logs')->delete();
        \DB::table('direction_exchange_min_price_logs')->truncate();
        DirectionExchangeMinPriceLog::insert($this->logErrors);


        if (!empty($this->logErrors)) {
            return response()->json([
                'status' => 1,
                'message' => 'Данные обновлены с ошибкой, проверьте лог'
            ]);
        }

        return response()->json([
            'status' => 0,
            'message' => 'Данные обновлены'
        ]);
    }

    /**
     * Обновление данных
     */
    public function update(int $num, Request $request)
    {
        if ($num == 0) {
            foreach ($request->min_price as $key => $value) {
                DirectionExchange::where([
                    ['status', '=', 1],
                    ['is_manual_min_price1', '=', 0],
                    ['id_currency1', $key],
                ])->update([
                    'min_price1' => $value,
                ]);
            }

            foreach ($request->max_price as $key => $value) {
                DirectionExchange::where([
                    ['status', '=', 1],
                    ['is_manual_max_price1', '=', 0],
                    ['id_currency1', $key],
                ])->update([
                    'max_price1' => $value,
                ]);
            }
        }

        if ($num == 1) {

            if(isset($request->min_price) and !is_null($request->min_price))
            {
                foreach ($request->min_price as $key => $value)
                {
                    DirectionExchange::where([
                        ['status', '=', 1],
                        ['is_manual_min_price2', '=', 0],
                        ['id_currency2', $key],
                    ])->update([
                        'min_price2' => $value,
                    ]);
                }
            }

            if(isset($request->max_price) and !is_null($request->max_price))
            {
                foreach ($request->max_price as $key => $value)
                {
                    DirectionExchange::where([
                        ['status', '=', 1],
                        ['is_manual_max_price2', '=', 0],
                        ['id_currency2', $key],
                    ])->update([
                        'max_price2' => $value,
                    ]);
                }
            }
        }

        return response()->json([
            'status' => 0,
            'message' => 'Данные обновлены'
        ]);
    }

    /**
     * Лог действий
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Foundation\Application|\Illuminate\Http\RedirectResponse
     */
    public function logs(Request $request)
    {
        $logs = DirectionExchangeMinPriceLog::orderBy('id', 'desc')->paginate(20);

        return view('admin.basic.direction_exchange.min_price_logs', compact('logs'));
    }
}
