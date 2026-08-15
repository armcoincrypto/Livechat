<?php

namespace App\Support;

use App\Models\Currency;
use App\Models\DirectionExchange;
use App\Models\HistoryUpdatedData;
use App\Models\OrderExchangeTotal;
use App\Models\Reserve;
use App\Models\Task;
use App\Models\TaskProfit;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use iEXPackages\ReferralSystem\Models\ReferralLog;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class Widgets
{
    /**
     * Получаем данные по виджету
     *
     * @param string $name
     * @param Request|null $request
     * @return array
     */
    public function getWidgetsById(string $name, Request $request = null): array
    {
        if(method_exists($this, $name)) {
            return $this->$name($request);
        }

        return [];
    }


    /**
     * @deprecated
    */
    protected function getCommonAmountExchanges()
    {
        // Суммы обменов за 3 дня
        $commonAmount = OrderExchangeTotal::selectRaw('created_at, sum(exchange_usd) as usd')
            ->orderByDesc('id')
            ->groupBy(\DB::raw("DATE_FORMAT(created_at, '%d-%m-%Y')"))
            ->limit(5)
            ->get();

        // Список ролей
        $list_roles = User::role(Role::all())->get();


        $response = $commonAmount->map(function ($item) use ($list_roles) {

            $timeName = '';
            if(Carbon::parse($item->created_at)->isToday()) {
                $timeName = 'Сегодня';
            } elseif (Carbon::parse($item->created_at)->isYesterday()) {
                $timeName = 'Вчера';
            } else {
                $timeName = Carbon::parse($item->created_at)->translatedFormat('d M Y');
            }

            return [
                'timeName' => $timeName,
                'usd' => (float)$item->usd,
                'managers' => $list_roles->map(function ($user) use($item)
                {
                    if($user->managerHistory('count', $item->created_at) > 0) {
                        return [
                            'name' => $user->name,
                            'count' => $user->managerHistory('count', $item->created_at)
                        ];
                    }
                })->filter()->all()
            ];
        });

        return [
            'items' => $response
        ];
    }

    protected function getParserUpdate()
    {
        $exportFiles = HistoryUpdatedData::where('type_update', '=', 'export_files')
            ->orderByDesc('id')->first();
        $exchange = HistoryUpdatedData::where('type_update', '=', 'export_exchange')->orderByDesc('id')->first();
        $bestchange = HistoryUpdatedData::where('type_update', '=', 'bestchange')->orderByDesc('id')->first();
        $courses = HistoryUpdatedData::where('type_update', '=', 'courses')->orderByDesc('id')->first();


        return [
            'exchange' => (isset($exchange) and !empty($exchange)) ? [
                'createdAt' => Carbon::parse($exchange['created_at'])->diffForHumans() ?? '',
                'totalNum' => $exchange['total_num'] ?? '',
                'countNum' => $exchange['count_num'] ?? '',
                'time' => $exchange['time'] ?? ''
            ] : [],
            'bestchange' => (isset($bestchange) and !empty($bestchange)) ? [
                'createdAt' => Carbon::parse($bestchange['created_at'])->diffForHumans() ?? '',
                'totalNum' => $bestchange['total_num'] ?? '',
                'countNum' => $bestchange['count_num'] ?? '',
                'time' => $bestchange['time'] ?? ''
            ] : [],
            'courses' => (isset($courses) and !empty($courses)) ? [
                'createdAt' => Carbon::parse($courses['created_at'])->diffForHumans() ?? '',
                'totalNum' => $courses['total_num'] ?? '',
                'countNum' => $courses['count_num'] ?? '',
                'time' => $courses['time'] ?? ''
            ] : [],
            'exportFiles' => (isset($exportFiles) and !empty($exportFiles)) ? [
                'createdAt' => Carbon::parse($exportFiles['created_at'])->diffForHumans() ?? '',
                'totalNum' => $exportFiles['total_num'] ?? '',
                'countNum' => $exportFiles['count_num'] ?? '',
                'time' => $exportFiles['time'] ?? ''
            ] : [],
        ];
    }


    /**
     * @deprecated
    */
    protected function getPopularDirections()
    {
        $m = \App\Models\Task::with([
            'direction_exchange' => function ($query) {
                $query->select('id' , 'tech_name');
            }
        ])->select('id_direction_exchange', DB::raw('count(*) * 100 / (select count(*) from tasks where status = 4) as count'))
            ->where('status', '=', 4)
            ->groupBy('id_direction_exchange')
            ->limit(7)
            ->get()->toArray();


        return [
            'items' => $m
        ];
    }

    /**
     * Получаем IP Адрес сервера
     * @return array
     */
    protected function getServerAddress()
    {
        return [
            'ip' => request()->server('SERVER_ADDR'),
        ];
    }

    /**
     * @deprecated
    */
    protected function getOrderProfit(Request $request)
    {
        $typeName = $request->value ?? 'day';

        $startDate = \Illuminate\Support\Carbon::today()->startOfMonth();
        $endDate = \Illuminate\Support\Carbon::today()->endOfMonth();

        if($typeName == 'monthly') {
            $startDate = \Illuminate\Support\Carbon::today()->startOfYear();
            $endDate = \Illuminate\Support\Carbon::today()->endOfYear();
        }


        if($typeName == 'monthly') {
            $order_profit = TaskProfit::selectRaw('MONTH(created_at) as date, sum(profit_usd) as usd')
                ->whereBetween('created_at', [$startDate, $endDate])->groupByRaw('MONTH(created_at)')
                ->get()->map(function($item) {
                    return [
                        'date' => Carbon::create()->day(1)->month($item->date)->translatedFormat('F'),
                        'usd' => $item->usd
                    ];
                })->keyBy('date');
        }elseif($typeName == 'yearly') {
            $order_profit = TaskProfit::selectRaw('YEAR(created_at) as date, sum(profit_usd) as usd')
                ->whereBetween('created_at', [$startDate, $endDate])->groupByRaw('YEAR(created_at)')
                ->get()->keyBy('date');
        } else {
            $order_profit = TaskProfit::selectRaw('DAY(created_at) as date, sum(profit_usd) as usd')
                ->whereBetween('created_at', [$startDate, $endDate])->groupByRaw('DAY(created_at)')
                ->get()->keyBy('date');
        }

        return [
            'profits' => $order_profit ?? [],
        ];
    }



    /**
     * @deprecated
     */
    protected function getOrderStat(Request $request)
    {
        $typeName = $request->value ?? 'day';

        $startDate = \Illuminate\Support\Carbon::today()->startOfMonth();
        $endDate = \Illuminate\Support\Carbon::today()->endOfMonth();

        if($typeName == 'monthly') {
            $startDate = \Illuminate\Support\Carbon::today()->startOfYear();
            $endDate = \Illuminate\Support\Carbon::today()->endOfYear();
        }


        if($typeName == 'monthly') {
            $orders = Task::selectRaw('MONTH(created_at) as date')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupByRaw('MONTH(created_at)')
                ->selectRaw('count(*) as total')
                ->selectRaw('count(case when status = 4 then 1 end) as success_orders')
                ->selectRaw('count(case when status = 5 then 1 end) as failed_orders')
                ->get()->keyBy('date')->map(function($item) {
                    return [
                        'date' => Carbon::create()->day(1)->month($item->date)->translatedFormat('F'),
                        'success_orders' => $item->success_orders,
                        'failed_orders' => $item->failed_orders,
                        'total' => $item->total
                    ];
                })->keyBy('date');
        } elseif($typeName == 'yearly') {
            $orders = Task::selectRaw('YEAR(created_at) as date')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupByRaw('YEAR(created_at)')
                ->selectRaw('count(*) as total')
                ->selectRaw('count(case when status = 4 then 1 end) as success_orders')
                ->selectRaw('count(case when status = 5 then 1 end) as failed_orders')
                ->get()->keyBy('date');
        } else {
            $orders = Task::selectRaw('DAY(created_at) as date')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupByRaw('DAY(created_at)')
                ->selectRaw('count(*) as total')
                ->selectRaw('count(case when status = 4 then 1 end) as success_orders')
                ->selectRaw('count(case when status = 5 then 1 end) as failed_orders')
                ->get()->keyBy('date');
        }


        return [
            'orders' => $orders,
        ];
    }

    protected function getAdminOnline()
    {
        $admin_users = User::where('last_activity_at', '>=', \Illuminate\Support\Carbon::now()->subMinutes(5))
            ->get()->filter(function ($item) {
                $page = explode('/', $item->current_page);

                return Arr::first($page) == config('iexexchanger.admin_folder');
            })->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'email' => $item->email,
                    'last_activity_at' => Carbon::parse($item->last_activity_at)->diffForHumans(),
                    'roles' => $item->roles()->pluck('title')->implode(' ')
                ];
            });


        return [
            'users' => $admin_users
        ];
    }

    protected function getTopPartners()
    {
        $start_day = \Illuminate\Support\Carbon::today()->startOfDay()->toDateTimeString();
        $end_day = Carbon::today()->endOfDay()->toDateTimeString();

        $referrals_logs = ReferralLog::with(['user_admin', 'user_admin.user_balance'])->select('id_user')
            ->selectRaw('count(*) as count_num')
            ->selectRaw('sum(`bonus_number`) as total_amount')
            ->selectRaw("count(case when created_at BETWEEN '{$start_day}' AND '{$end_day}' then 1 end) as today")
            ->groupBy('id_user')
            ->orderByDesc('count_num')
            ->limit(3)
            ->get()->map(function ($item) {
                return [
                    'user_admin' => isset($item->user_admin) ? [
                        'id' => $item->user_admin->id,
                        'name' => $item->user_admin->name,
                        'email' => $item->user_admin->email,
                    ] : [],

                    'count_num' => $item->count_num,
                    'total' => $item->user_admin?->user_balance->balance,
                    'amount' => $item->total_amount,
                    'today' => $item->today
                ];
            });

        return [
            'referrals_logs' => $referrals_logs,
        ];
    }


    protected function getTotalProfit()
    {
        $order_profit = TaskProfit::selectRaw('created_at, sum(profit_usd) as usd')
            ->orderBy('id', 'desc')->groupBy(\DB::raw("DATE_FORMAT(created_at, '%d-%m-%Y')"))
            ->cursor();

        return response()->json($order_profit);
    }


    /**
     * @deprecated
    */
    protected function getTotalAmountExchanges()
    {
        $cache_id = (iEXSetting('wa_currency_exchange_type') == 0 ? 'currency-exchange-widget-current' : 'currency-exchange-widget-fixed');
        $response = \Cache::remember($cache_id, \Illuminate\Support\Carbon::now()->addMinutes(20), function () {
            $currencies = Currency::with(['currency_analytics', 'code_currency'])->active()->get();

            $in_amount = 0;
            $out_amount = 0;

            if (iEXSetting('wa_currency_exchange_type') == 0) {
                foreach ($currencies as $currency) {
                    if (isset($currency->currency_analytics) and isset($currency->code_currency)) {
                        if (! empty($currency->currency_analytics->in_amount)) {
                            $in_amount += convert_to_usd($currency->code_currency->name, $currency->currency_analytics->in_amount);
                        }

                        if (! empty($currency->currency_analytics->out_amount)) {
                            $out_amount += convert_to_usd($currency->code_currency->name, $currency->currency_analytics->out_amount);
                        }
                    }
                }
            } else {
                foreach ($currencies as $currency) {
                    if (isset($currency->currency_analytics) and isset($currency->code_currency)) {
                        $in_amount += $currency->currency_analytics->in_amount_usd;
                        $out_amount += $currency->currency_analytics->out_amount_usd;
                    }
                }
            }

            return [
                'in' => $in_amount,
                'out' => $out_amount,
            ];
        });


        return $response;
    }


    /***
     * @deprecated
    */
    protected function getTotalReserve()
    {
        $convert_reserve = 0;
        try {
            $reserves = Reserve::whereHas('currency', function ($query) {
                $query->where('status', '=', 0);
            })->where('id_main', '=', 0)->orderBy('sorting')->get();

            $convert_reserve = \Cache::remember('total_reserves', \Illuminate\Support\Carbon::now()->addMinutes(20), function () use ($reserves) {
                return $reserves->map(function ($item) {
                    return (float) convert_to_usd($item->currency->code_currency->name, $item->summa);
                })->sum();
            });
        } catch (\Exception $exception) {

        }

        return [
            'total' => $convert_reserve ?? 0
        ];
    }


    protected function getUserStat()
    {
        $start_day = Carbon::today()->startOfDay()->toDateTimeString();
        $end_day = Carbon::today()->endOfDay()->toDateTimeString();

        $order_count = Task::selectRaw('count(*) as total')
            ->selectRaw("count(case when status = 4 and updated_at BETWEEN '{$start_day}' AND '{$end_day}' then 1 when created_at BETWEEN '{$start_day}' AND '{$end_day}' then 1 end) as success")
            ->first();

        $users_count = User::selectRaw('count(*) as total')
            ->selectRaw("count(case when created_at BETWEEN '{$start_day}' AND '{$end_day}' then 1 end) as today")
            ->selectRaw('count(case when email_verified_at IS NOT NULL then 1 end) as verified')
            ->selectRaw('count(case when banned_at IS NOT NULL then 1 end) as banned')
            ->first();

        $roles_count = User::permission('allow_admin')->count();

        $direction_count = DirectionExchange::selectRaw('count(*) as total')
            ->selectRaw('count(case when status = 1 then 1 end) as active')
            ->first();

        return [
            'users_count' => $users_count,
            'roles_count' => $roles_count,
            'order_count' => $order_count,
            'direction_count' => $direction_count,
        ];
    }
}
