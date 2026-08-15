<?php

namespace iEXPackages\Analytics\Http\Controllers\Exchanges;

use App\Http\Controllers\Controller;
use App\Models\OrderExchangeStatDaily;
use Carbon\Carbon;
use iEXPackages\Analytics\Services\Exchanges\ExchangeOrdersStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrdersAnalyticsController extends Controller
{
    public function __construct(
        protected ExchangeOrdersStatsService $service
    ) {
    }

    /**
     * Полный дашборд (сегодня / вчера / недели / месяцы / год / проценты).
     */
    public function dashboard(): JsonResponse
    {
        $data = $this->service->getDashboardStats();

        return response()->json($data);
    }

    /**
     * Обновлённый getOrderStat(), но уже на основе order_exchange_stats_daily.
     *
     * value = day | monthly | yearly
     */
    public function getOrderStat(Request $request): JsonResponse
    {
        $typeName = $request->input('value', 'day');

        $tz = config('exchange_stats.timezone', config('app.timezone', 'UTC'));
        $today = Carbon::today($tz);

        // по умолчанию — текущий месяц
        $startDate = $today->copy()->startOfMonth();
        $endDate   = $today->copy()->endOfMonth();

        if ($typeName === 'monthly') {
            $startDate = $today->copy()->startOfYear();
            $endDate   = $today->copy()->endOfYear();
        } elseif ($typeName === 'yearly') {
            // например, последние 5 лет, включая текущий
            $startDate = $today->copy()->subYears(4)->startOfYear();
            $endDate   = $today->copy()->endOfYear();
        }

        if ($typeName === 'monthly') {
            $orders = OrderExchangeStatDaily::query()
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->selectRaw('YEAR(date) as y')
                ->selectRaw('MONTH(date) as m')
                ->selectRaw('SUM(total_count) as total')
                ->selectRaw('SUM(completed_count) as success_orders')
                ->selectRaw('SUM(rejected_count) as failed_orders')
                ->groupByRaw('YEAR(date), MONTH(date)')
                ->orderBy('y')
                ->orderBy('m')
                ->get()
                ->map(function ($item) use ($tz) {
                    $monthCarbon = Carbon::create($item->y, $item->m, 1, 0, 0, 0, $tz);

                    return [
                        'date'           => $monthCarbon->translatedFormat('F'),
                        'year'           => (int) $item->y,
                        'month'          => (int) $item->m,
                        'success_orders' => (int) $item->success_orders,
                        'failed_orders'  => (int) $item->failed_orders,
                        'total'          => (int) $item->total,
                    ];
                })
                ->keyBy(function ($item) {
                    return $item['month'];
                });
        } elseif ($typeName === 'yearly') {
            // по годам
            $orders = OrderExchangeStatDaily::query()
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->selectRaw('YEAR(date) as year')
                ->selectRaw('SUM(total_count) as total')
                ->selectRaw('SUM(completed_count) as success_orders')
                ->selectRaw('SUM(rejected_count) as failed_orders')
                ->groupByRaw('YEAR(date)')
                ->orderBy('year')
                ->get()
                ->map(function ($item) {
                    return [
                        'date'           => (int) $item->year,   // для графика/ключа
                        'year'           => (int) $item->year,   // явное поле
                        'total'          => (int) $item->total,
                        'success_orders' => (int) $item->success_orders,
                        'failed_orders'  => (int) $item->failed_orders,
                    ];
                })
                ->keyBy('date');
        } else {
            // режим day — по дням текущего месяца
            $orders = OrderExchangeStatDaily::query()
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->selectRaw('DATE(date) as stat_date')
                ->selectRaw('DAY(date) as day')
                ->selectRaw('SUM(total_count) as total')
                ->selectRaw('SUM(completed_count) as success_orders')
                ->selectRaw('SUM(rejected_count) as failed_orders')
                ->groupByRaw('DATE(date)')
                ->orderBy('stat_date')
                ->get()
                ->map(function ($item) {
                    return [
                        'date'           => (int) $item->day,
                        'stat_date'      => $item->stat_date,
                        'total'          => (int) $item->total,
                        'success_orders' => (int) $item->success_orders,
                        'failed_orders'  => (int) $item->failed_orders,
                    ];
                })
                ->keyBy('date');
        }

        return response()->json([
            'orders' => $orders,
        ]);
    }
}
