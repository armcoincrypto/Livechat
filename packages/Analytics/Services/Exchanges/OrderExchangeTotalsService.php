<?php
namespace iEXPackages\Analytics\Services\Exchanges;


use App\Models\OrderExchangeTotal;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class OrderExchangeTotalsService
{
    /**
     * Сводка за период + сравнение с прошлым периодом.
     */
    public function getSummary(Carbon $from, Carbon $to, ?string $deviceType = null): array
    {
        // Текущий период
        $current = $this->aggregatePeriod($from, $to, $deviceType);

        // Предыдущий период такой же длины
        $days = $from->diffInDays($to) + 1;
        $prevTo = (clone $from)->subDay();
        $prevFrom = (clone $prevTo)->subDays($days - 1);

        $previous = $this->aggregatePeriod($prevFrom, $prevTo, $deviceType);

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'total' => $current,
            'previous' => $previous,
            'change' => [
                'total_usd_percent'  => $this->percentChange($previous['total_usd'], $current['total_usd']),
                'orders_count_percent' => $this->percentChange($previous['orders_count'], $current['orders_count']),
                'avg_check_usd_percent' => $this->percentChange($previous['avg_check_usd'], $current['avg_check_usd']),
            ],
        ];
    }

    /**
     * Динамика по дням (линейный график): сумма, кол-во, средний чек.
     */
    public function getDailyDynamics(Carbon $from, Carbon $to, ?string $deviceType = null): array
    {
        $query = OrderExchangeTotal::query()
            ->whereBetween('order_exchange_totals.created_at', [$from->startOfDay(), $to->endOfDay()]);

        if ($deviceType !== null && $deviceType !== '') {
            $query
                ->join('tasks', 'order_exchange_totals.id_task', '=', 'tasks.id')
                ->leftJoin('tasks_meta', 'tasks.id', '=', 'tasks_meta.task_id')
                ->where('tasks_meta.device_type', $deviceType);
        }

        $rows = $query
            ->selectRaw('DATE(order_exchange_totals.created_at) as day, COUNT(order_exchange_totals.id) as orders_count, SUM(order_exchange_totals.exchange_usd) as total_usd')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // Приводим к полному периоду: заполняем дни без заявок нулями
        $period = CarbonPeriod::create((clone $from)->startOfDay(), (clone $to)->startOfDay());
        $byDay = $rows->keyBy('day');

        $items = [];
        foreach ($period as $date) {
            $day = $date->toDateString();

            if (isset($byDay[$day])) {
                $totalUsd = (float) $byDay[$day]->total_usd;
                $orders   = (int) $byDay[$day]->orders_count;
            } else {
                $totalUsd = 0.0;
                $orders   = 0;
            }

            $items[] = [
                'date'          => $day,
                'total_usd'     => $totalUsd,
                'orders_count'  => $orders,
                'avg_check_usd' => $orders > 0 ? $this->roundUsd($totalUsd / $orders) : 0.0,
            ];
        }

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'items' => $items,
        ];
    }

    /**
     * Статистика по менеджерам за период (без постраничной навигации)
     */
    /**
     * Топ дней и «проблемные дни» за период.
     * Используется динамика по дням, но добавляет:
     * - top_days: топ-3 дней по обороту
     * - low_days: дни с оборотом < 50% среднего
     */
    public function getTopAndLowDays(Carbon $from, Carbon $to, ?string $deviceType = null): array
    {
        // Получаем дневную динамику (она уже учитывает deviceType)
        $daily = $this->getDailyDynamics($from, $to, $deviceType);
        $items = collect($daily['items']);

        if ($items->count() === 0) {
            return [
                'period' => $daily['period'],
                'top_days' => [],
                'low_days' => [],
                'avg_total_usd' => 0,
            ];
        }

        // Средний оборот за период
        $avgUsd = (float) $items->avg('total_usd');

        // Топ-3 дней по обороту
        $topDays = $items
            ->sortByDesc('total_usd')
            ->take(3)
            ->values()
            ->all();

        // «Проблемные дни»: где оборот < 50% среднего
        $lowDays = $items
            ->filter(fn($d) => $d['total_usd'] < ($avgUsd * 0.5))
            ->sortBy('total_usd')
            ->take(3)
            ->values()
            ->all();

        return [
            'period' => $daily['period'],
            'top_days' => $topDays,
            'low_days' => $lowDays,
            'avg_total_usd' => $avgUsd,
        ];
    }

    /**
     * Статистика по менеджерам за период (без постраничной навигации)
     */
    public function getManagersStats(Carbon $from, Carbon $to, ?string $deviceType = null): array
    {
        $query = OrderExchangeTotal::query()
            ->join('tasks', 'order_exchange_totals.id_task', '=', 'tasks.id')
            ->join('users', 'tasks.id_who_completed', '=', 'users.id')
            ->leftJoin('tasks_meta', 'tasks.id', '=', 'tasks_meta.task_id')
            ->whereBetween('order_exchange_totals.created_at', [$from->startOfDay(), $to->endOfDay()]);

        if ($deviceType !== null && $deviceType !== '') {
            $query->where('tasks_meta.device_type', $deviceType);
        }

        $rows = $query
            ->selectRaw('
            users.id   as manager_id,
            users.name as manager_name,
            users.email as manager_email,
            COUNT(order_exchange_totals.id) as orders_count,
            SUM(order_exchange_totals.exchange_usd) as total_usd
        ')
            ->groupBy('manager_id', 'manager_name', 'manager_email')
            ->orderByDesc('total_usd')
            ->get();

        $totalUsdAll    = (float) $rows->sum('total_usd');
        $totalOrdersAll = (int) $rows->sum('orders_count');

        $items = $rows->map(function ($row) use ($totalUsdAll, $totalOrdersAll) {
            $totalUsd = (float) $row->total_usd;
            $orders   = (int) $row->orders_count;

            return [
                'manager_id'        => (int) $row->manager_id,
                'manager_name'      => (string) $row->manager_name,
                'manager_email'     => (string) $row->manager_email,
                'orders_count'      => $orders,
                'total_usd'         => $this->roundUsd($totalUsd),
                'share_usd_percent' => $totalUsdAll > 0 ? $this->roundPercent($totalUsd * 100 / $totalUsdAll) : 0.0,
                'share_orders_percent' => $totalOrdersAll > 0 ? $this->roundPercent($orders * 100 / $totalOrdersAll) : 0.0,
            ];
        })->values()->all();

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'total' => [
                'total_usd'    => $this->roundUsd($totalUsdAll),
                'orders_count' => $totalOrdersAll,
            ],
            'items' => $items,
        ];
    }

    /**
     * Список дней + внутри каждого дня — разбивка по менеджерам.
     * Удобно для drill-down: клик по дню → детали по менеджерам.
     */
    public function getDailyWithManagers(Carbon $from, Carbon $to): array
    {
        $rows = OrderExchangeTotal::query()
            ->join('tasks', 'order_exchange_totals.id_task', '=', 'tasks.id')
            ->join('users', 'tasks.id_who_completed', '=', 'users.id')
            ->whereBetween('order_exchange_totals.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('
                DATE(order_exchange_totals.created_at) as day,
                users.id   as manager_id,
                users.name as manager_name,
                COUNT(order_exchange_totals.id) as orders_count,
                SUM(order_exchange_totals.exchange_usd) as total_usd
            ')
            ->groupBy('day', 'manager_id', 'manager_name')
            ->orderBy('day')
            ->get();

        /** @var Collection<string, Collection> $groupedByDay */
        $groupedByDay = $rows->groupBy('day');

        $period = CarbonPeriod::create((clone $from)->startOfDay(), (clone $to)->startOfDay());

        $days = [];
        foreach ($period as $date) {
            $day = $date->toDateString();
            $dayRows = $groupedByDay->get($day, collect());

            $totalUsd = (float) $dayRows->sum('total_usd');
            $totalOrders = (int) $dayRows->sum('orders_count');

            $managers = $dayRows->map(function ($row) use ($totalUsd, $totalOrders) {
                $managerUsd  = (float) $row->total_usd;
                $managerOrders = (int) $row->orders_count;

                return [
                    'manager_id'        => (int) $row->manager_id,
                    'manager_name'      => (string) $row->manager_name,
                    'orders_count'      => $managerOrders,
                    'total_usd'         => $this->roundUsd($managerUsd),
                    'share_usd_percent' => $totalUsd > 0 ? $this->roundPercent($managerUsd * 100 / $totalUsd) : 0.0,
                    'share_orders_percent' => $totalOrders > 0 ? $this->roundPercent($managerOrders * 100 / $totalOrders) : 0.0,
                ];
            })->values()->all();

            $days[] = [
                'date'         => $day,
                'total_usd'    => $this->roundUsd($totalUsd),
                'orders_count' => $totalOrders,
                'managers'     => $managers,
            ];
        }

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'days' => $days,
        ];
    }

    /**
     * Базовый агрегат за период: сумма, кол-во, средний чек.
     */
    protected function aggregatePeriod(Carbon $from, Carbon $to, ?string $deviceType = null): array
    {
        $query = OrderExchangeTotal::query()
            ->whereBetween('order_exchange_totals.created_at', [$from->startOfDay(), $to->endOfDay()]);

        if ($deviceType !== null && $deviceType !== '') {
            $query
                ->join('tasks', 'order_exchange_totals.id_task', '=', 'tasks.id')
                ->leftJoin('tasks_meta', 'tasks.id', '=', 'tasks_meta.task_id')
                ->where('tasks_meta.device_type', $deviceType);
        }

        $row = $query
            ->selectRaw('COUNT(order_exchange_totals.id) as orders_count, COALESCE(SUM(order_exchange_totals.exchange_usd), 0) as total_usd')
            ->first();

        $totalUsd = $row ? (float) $row->total_usd : 0.0;
        $orders   = $row ? (int) $row->orders_count : 0;

        return [
            'total_usd'     => $this->roundUsd($totalUsd),
            'orders_count'  => $orders,
            'avg_check_usd' => $orders > 0 ? $this->roundUsd($totalUsd / $orders) : 0.0,
        ];
    }

    protected function percentChange(float $old, float $new): float
    {
        if ($old == 0.0 && $new == 0.0) {
            return 0.0;
        }

        if ($old == 0.0) {
            return 100.0;
        }

        return $this->roundPercent(($new - $old) * 100 / $old);
    }

    protected function roundUsd(float $value): float
    {
        return round($value, 8); // если нужны до 8 знаков после запятой
    }

    protected function roundPercent(float $value): float
    {
        return round($value, 2);
    }
}
