<?php

namespace iEXPackages\Analytics\Services\Exchanges;


use App\Models\OrderExchangeStatDaily;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

class ExchangeOrdersStatsService
{
    /**
     * Получить полный набор статистики для дашборда обменов.
     */
    public function getDashboardStats(?Carbon $now = null): array
    {
        $tz  = Config::get('exchange_stats.timezone', config('app.timezone', 'UTC'));
        $now = $now ? $now->copy()->setTimezone($tz) : Carbon::now($tz);

        $today       = $now->copy()->startOfDay();
        $yesterday   = $today->copy()->subDay();
        $dayBefore   = $today->copy()->subDays(2);

        $weekStart   = $today->copy()->startOfWeek(); // по умолчанию понедельник
        $weekEnd     = $today->copy()->endOfWeek();

        $lastWeekStart = $weekStart->copy()->subWeek();
        $lastWeekEnd   = $weekEnd->copy()->subWeek();

        $monthStart  = $today->copy()->startOfMonth();
        $monthEnd    = $today->copy()->endOfMonth();

        $lastMonthStart = $monthStart->copy()->subMonth()->startOfMonth();
        $lastMonthEnd   = $lastMonthStart->copy()->endOfMonth();

        $yearStart   = $today->copy()->startOfYear();
        $yearEnd     = $today->copy()->endOfYear();

        // Загрузим все записи за год + небольшой запас
        $minDate = $yearStart->copy()->subMonths(1);
        $maxDate = $yearEnd->copy()->addMonths(1);

        /** @var Collection<int, OrderExchangeStatDaily> $rows */
        $rows = OrderExchangeStatDaily::query()
            ->whereBetween('date', [$minDate->toDateString(), $maxDate->toDateString()])
            ->orderBy('date')
            ->get();

        $byDate = $rows->keyBy(fn (OrderExchangeStatDaily $row) => $row->date->toDateString());

        // helper для диапазонов
        $rangeStats = function (Carbon $from, Carbon $to) use ($byDate): array {
            $cursor = $from->copy();
            $sum = [
                'total_count'      => 0,
                'completed_count'  => 0,
                'rejected_count'   => 0,
                'processing_count' => 0,
            ];

            while ($cursor->lte($to)) {
                $key = $cursor->toDateString();
                if ($byDate->has($key)) {
                    /** @var OrderExchangeStatDaily $row */
                    $row = $byDate->get($key);

                    $sum['total_count']      += (int) $row->total_count;
                    $sum['completed_count']  += (int) $row->completed_count;
                    $sum['rejected_count']   += (int) $row->rejected_count;
                    $sum['processing_count'] += (int) $row->processing_count;
                }

                $cursor->addDay();
            }

            return $sum;
        };

        // дни
        $todayStats     = $rangeStats($today, $today);
        $yesterdayStats = $rangeStats($yesterday, $yesterday);
        $dayBeforeStats = $rangeStats($dayBefore, $dayBefore);

        // недели
        $weekStats     = $rangeStats($weekStart, $weekEnd);
        $lastWeekStats = $rangeStats($lastWeekStart, $lastWeekEnd);

        // месяцы
        $monthStats     = $rangeStats($monthStart, $monthEnd);
        $lastMonthStats = $rangeStats($lastMonthStart, $lastMonthEnd);

        // год
        $yearStats = $rangeStats($yearStart, $yearEnd);

        // Calculate rates for each period
        $todayRates      = $this->computeRates($todayStats);
        $yesterdayRates  = $this->computeRates($yesterdayStats);
        $dayBeforeRates  = $this->computeRates($dayBeforeStats);
        $weekRates       = $this->computeRates($weekStats);
        $lastWeekRates   = $this->computeRates($lastWeekStats);
        $monthRates      = $this->computeRates($monthStats);
        $lastMonthRates  = $this->computeRates($lastMonthStats);
        $yearRates       = $this->computeRates($yearStats);

        // Статистика по типам клиентов (новые / возвращающиеся)
        $weekCustomers  = $this->computeCustomerTypes($weekStart, $weekEnd, $tz);
        $monthCustomers = $this->computeCustomerTypes($monthStart, $monthEnd, $tz);
        $yearCustomers  = $this->computeCustomerTypes($yearStart, $yearEnd, $tz);

        // Статистика по устройствам
        $weekDevices  = $this->computeDeviceStats($weekStart, $weekEnd);
        $monthDevices = $this->computeDeviceStats($monthStart, $monthEnd);
        $yearDevices  = $this->computeDeviceStats($yearStart, $yearEnd);

        // помесячно + проценты
        $monthsStats = $this->buildMonthlyStatsWithDiff($yearStart, $yearEnd, $byDate);

        // ---- НОВОЕ: аналитика года ----
        $yearTotal = (int) ($yearStats['total_count'] ?? 0);
        $bestMonthSummary = null;
        $bestDaySummary   = null;

        // Лучший месяц по количеству заявок
        if (!empty($monthsStats['items']) && $yearTotal > 0) {
            $bestMonth = null;

            foreach ($monthsStats['items'] as $item) {
                $monthTotal = (int) ($item['stats']['total_count'] ?? 0);

                if ($monthTotal <= 0) {
                    continue;
                }

                if ($bestMonth === null || $monthTotal > (int) ($bestMonth['stats']['total_count'] ?? 0)) {
                    $bestMonth = $item;
                }
            }

            if ($bestMonth !== null) {
                $monthTotal = (int) ($bestMonth['stats']['total_count'] ?? 0);
                $share = $yearTotal > 0 ? round($monthTotal / $yearTotal * 100, 2) : null;

                $bestMonthSummary = [
                    'month'         => $bestMonth['month'],
                    'label'         => $bestMonth['label'],
                    'from'          => $bestMonth['from'],
                    'to'            => $bestMonth['to'],
                    'total_count'   => $monthTotal,
                    'share_percent' => $share,
                ];
            }
        }

        // Лучший день в году по количеству заявок
        if ($yearTotal > 0) {
            $bestDayDate  = null;
            $bestDayCount = 0;

            $cursor = $yearStart->copy();
            while ($cursor->lte($yearEnd)) {
                $key = $cursor->toDateString();

                if ($byDate->has($key)) {
                    /** @var OrderExchangeStatDaily $row */
                    $row = $byDate->get($key);
                    $cnt = (int) $row->total_count;

                    if ($cnt > $bestDayCount) {
                        $bestDayCount = $cnt;
                        $bestDayDate  = $key;
                    }
                }

                $cursor->addDay();
            }

            if ($bestDayDate !== null && $bestDayCount > 0) {
                $bestDaySummary = [
                    'date'          => $bestDayDate,
                    'total_count'   => $bestDayCount,
                    'share_percent' => round($bestDayCount / $yearTotal * 100, 2),
                ];
            }
        }

        // -------------------------------

        return [
            'meta' => [
                'timezone'     => $tz,
                'generated_at' => $now->toDateTimeString(),
            ],
            'periods' => [
                'today' => [
                    'label'      => 'Сегодня',
                    'date_from'  => $today->toDateString(),
                    'date_to'    => $today->toDateString(),
                    'stats'      => $todayStats,
                    'rates'      => $todayRates,
                ],
                'yesterday' => [
                    'label'      => 'Вчера',
                    'date_from'  => $yesterday->toDateString(),
                    'date_to'    => $yesterday->toDateString(),
                    'stats'      => $yesterdayStats,
                    'rates'      => $yesterdayRates,
                ],
                'day_before_yesterday' => [
                    'label'      => 'Позавчера',
                    'date_from'  => $dayBefore->toDateString(),
                    'date_to'    => $dayBefore->toDateString(),
                    'stats'      => $dayBeforeStats,
                    'rates'      => $dayBeforeRates,
                ],
                'this_week' => [
                    'label'      => 'Текущая неделя',
                    'date_from'  => $weekStart->toDateString(),
                    'date_to'    => $weekEnd->toDateString(),
                    'stats'      => $weekStats,
                    'rates'      => $weekRates,
                    'customers'  => $weekCustomers,
                    'devices'    => $weekDevices,
                ],
                'last_week' => [
                    'label'      => 'Прошлая неделя',
                    'date_from'  => $lastWeekStart->toDateString(),
                    'date_to'    => $lastWeekEnd->toDateString(),
                    'stats'      => $lastWeekStats,
                    'rates'      => $lastWeekRates,
                ],
                'this_month' => [
                    'label'      => 'Текущий месяц',
                    'date_from'  => $monthStart->toDateString(),
                    'date_to'    => $monthEnd->toDateString(),
                    'stats'      => $monthStats,
                    'rates'      => $monthRates,
                    'customers'  => $monthCustomers,
                    'devices'    => $monthDevices,
                ],
                'last_month' => [
                    'label'      => 'Прошлый месяц',
                    'date_from'  => $lastMonthStart->toDateString(),
                    'date_to'    => $lastMonthEnd->toDateString(),
                    'stats'      => $lastMonthStats,
                    'rates'      => $lastMonthRates,
                ],
                'this_year' => [
                    'label'      => 'Текущий год',
                    'date_from'  => $yearStart->toDateString(),
                    'date_to'    => $yearEnd->toDateString(),
                    'stats'      => $yearStats,
                    'rates'      => $yearRates,
                    'customers'  => $yearCustomers,
                    'devices'    => $yearDevices,
                ],
            ],
            'months' => $monthsStats,
            'year_summary' => [
                'total_orders' => $yearTotal,
                'best_month'   => $bestMonthSummary,
                'best_day'     => $bestDaySummary,
            ],
        ];
    }

    /**
     * Помесячная статистика + проценты изменения к предыдущему месяцу.
     */
    protected function buildMonthlyStatsWithDiff(
        Carbon $yearStart,
        Carbon $yearEnd,
        Collection $byDate
    ): array {
        $currentYear = $yearStart->year;
        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $monthStart = (clone $yearStart)->setMonth($month)->startOfMonth();
            $monthEnd   = (clone $monthStart)->endOfMonth();

            if ($monthStart->lt($yearStart) || $monthStart->year !== $currentYear) {
                continue;
            }
            if ($monthStart->gt($yearEnd)) {
                break;
            }

            $cursor = $monthStart->copy();
            $sum = [
                'total_count'      => 0,
                'completed_count'  => 0,
                'rejected_count'   => 0,
                'processing_count' => 0,
            ];

            while ($cursor->lte($monthEnd)) {
                $key = $cursor->toDateString();

                if ($byDate->has($key)) {
                    /** @var OrderExchangeStatDaily $row */
                    $row = $byDate->get($key);

                    $sum['total_count']      += (int) $row->total_count;
                    $sum['completed_count']  += (int) $row->completed_count;
                    $sum['rejected_count']   += (int) $row->rejected_count;
                    $sum['processing_count'] += (int) $row->processing_count;
                }

                $cursor->addDay();
            }

            $rates = $this->computeRates($sum);

            $months[$month] = [
                'month'  => $month,
                'label'  => $monthStart->isoFormat('MMMM'),
                'from'   => $monthStart->toDateString(),
                'to'     => $monthEnd->toDateString(),
                'stats'  => $sum,
                'change_percent' => [
                    'total_count'      => null,
                    'completed_count'  => null,
                    'rejected_count'   => null,
                    'processing_count' => null,
                ],
                'rates'  => $rates,
            ];
        }

        // проценты
        for ($month = 1; $month <= 12; $month++) {
            if (!isset($months[$month])) {
                continue;
            }

            $current = $months[$month]['stats'];

            if ($month === 1 || !isset($months[$month - 1])) {
                continue;
            }

            $previous = $months[$month - 1]['stats'];

            $months[$month]['change_percent'] = [
                'total_count'      => $this->calcPercentDiff($previous['total_count'], $current['total_count']),
                'completed_count'  => $this->calcPercentDiff($previous['completed_count'], $current['completed_count']),
                'rejected_count'   => $this->calcPercentDiff($previous['rejected_count'], $current['rejected_count']),
                'processing_count' => $this->calcPercentDiff($previous['processing_count'], $current['processing_count']),
            ];
        }

        return [
            'year'  => $currentYear,
            'items' => array_values($months),
        ];
    }

    /**
     * Calculate conversion and reject rates from stats array.
     */
    protected function computeRates(array $stats): array
    {
        $total     = (int) ($stats['total_count'] ?? 0);
        $completed = (int) ($stats['completed_count'] ?? 0);
        $rejected  = (int) ($stats['rejected_count'] ?? 0);

        if ($total <= 0) {
            return [
                'conversion_rate' => null,
                'reject_rate'     => null,
            ];
        }

        $conversion = $completed / $total * 100;
        $reject     = $rejected / $total * 100;

        return [
            'conversion_rate' => round($conversion, 2),
            'reject_rate'     => round($reject, 2),
        ];
    }

    /**
     * Статистика по типам клиентов за период: новые vs возвращающиеся.
     *
     * new_users         — кол-во уникальных клиентов, чей первый заказ в системе попал в период.
     * returning_users   — уникальные клиенты, у которых были заявки в период, но первый заказ раньше.
     * new_orders        — количество заявок периода от новых клиентов.
     * returning_orders  — количество заявок периода от возвращающихся.
     */
    protected function computeCustomerTypes(Carbon $from, Carbon $to, string $tz): array
    {
        // Приводим границы к UTC, предполагая, что created_at хранится в UTC
        $fromUtc = $from->copy()->setTimezone('UTC');
        $toUtc   = $to->copy()->setTimezone('UTC');

        // Заявки в период с id_user
        $tasksInPeriod = Task::query()
            ->whereBetween('created_at', [$fromUtc, $toUtc])
            ->whereNotNull('id_user')
            ->where('id_user', '>', 0)
            ->get(['id', 'id_user', 'created_at']);

        if ($tasksInPeriod->isEmpty()) {
            return [
                'new_users'        => 0,
                'returning_users'  => 0,
                'new_orders'       => 0,
                'returning_orders' => 0,
            ];
        }

        $userIds = $tasksInPeriod->pluck('id_user')->unique()->values();

        // Минимальная дата заявки по каждому пользователю (первый заказ в системе)
        $firstOrders = Task::query()
            ->whereIn('id_user', $userIds)
            ->selectRaw('id_user, MIN(created_at) as first_at')
            ->groupBy('id_user')
            ->get()
            ->keyBy('id_user');

        $newUsers        = 0;
        $returningUsers  = 0;
        $newOrders       = 0;
        $returningOrders = 0;

        // Классифицируем пользователей как новых / возвращающихся
        $userType = [];

        foreach ($userIds as $userId) {
            $firstAt = $firstOrders->get($userId)?->first_at ?? null;
            if ($firstAt === null) {
                // На всякий случай считаем как возвращающегося
                $userType[$userId] = 'returning';
                $returningUsers++;
                continue;
            }

            $firstAtCarbon = Carbon::parse($firstAt);

            if ($firstAtCarbon->between($fromUtc, $toUtc)) {
                $userType[$userId] = 'new';
                $newUsers++;
            } else {
                $userType[$userId] = 'returning';
                $returningUsers++;
            }
        }

        // Считаем кол-во заявок по типам
        foreach ($tasksInPeriod as $task) {
            $uId  = $task->user_id;
            $type = $userType[$uId] ?? 'returning';

            if ($type === 'new') {
                $newOrders++;
            } else {
                $returningOrders++;
            }
        }

        return [
            'new_users'        => $newUsers,
            'returning_users'  => $returningUsers,
            'new_orders'       => $newOrders,
            'returning_orders' => $returningOrders,
        ];
    }

    /**
     * (curr - prev) / prev * 100
     */
    protected function calcPercentDiff(int|float $prev, int|float $curr): ?float
    {
        if ($prev === 0) {
            if ($curr === 0) {
                return null;
            }

            // Рост с 0 до >0 — на фронте можно отрисовать как "∞" или "рост с 0"
            return null;
        }

        $diff = ($curr - $prev) / $prev * 100;

        return round($diff, 2);
    }

    /**
     * Статистика по устройствам (desktop, mobile, android, ios, other) за период.
     * Использует device_type если задан, иначе user_agent.
     */
    protected function computeDeviceStats(Carbon $from, Carbon $to): array
    {
        $fromUtc = $from->copy()->setTimezone('UTC');
        $toUtc   = $to->copy()->setTimezone('UTC');

        $tasks = Task::query()
            ->with('meta')
            ->whereBetween('created_at', [$fromUtc, $toUtc])
            ->whereHas('meta', function ($q) {
                $q->where(function ($qq) {
                    $qq->whereNotNull('device_type')
                       ->orWhereNotNull('user_agent');
                });
            })
            ->get();

        if ($tasks->isEmpty()) {
            return [
                'desktop' => 0,
                'mobile'  => 0,
                'android' => 0,
                'ios'     => 0,
                'other'   => 0,
            ];
        }

        $desktop = 0;
        $mobile  = 0;
        $android = 0;
        $ios     = 0;
        $other   = 0;

        foreach ($tasks as $task) {
            $meta = $task->meta;
            $deviceType = $meta?->device_type ? mb_strtolower($meta->device_type) : '';
            $ua = $meta?->user_agent ? mb_strtolower($meta->user_agent) : '';

            // 1) Пробуем использовать device_type, если он задан
            if ($deviceType !== '') {
                if (in_array($deviceType, ['android'], true)) {
                    $android++;
                    $mobile++;
                } elseif (in_array($deviceType, ['ios', 'iphone', 'ipad'], true)) {
                    $ios++;
                    $mobile++;
                } elseif (in_array($deviceType, ['mobile'], true)) {
                    $mobile++;
                } elseif (in_array($deviceType, ['desktop', 'pc', 'laptop'], true)) {
                    $desktop++;
                } else {
                    $other++;
                }

                continue;
            }

            // 2) Если device_type нет — пробуем user_agent
            if ($ua === '') {
                $other++;
                continue;
            }

            $isAndroid = str_contains($ua, 'android');
            $isIPhone  = str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ios');
            $isMobile  = $isAndroid || $isIPhone || str_contains($ua, 'mobile');

            if ($isAndroid) {
                $android++;
                $mobile++;
            } elseif ($isIPhone) {
                $ios++;
                $mobile++;
            } elseif ($isMobile) {
                $mobile++;
            } elseif (str_contains($ua, 'windows') || str_contains($ua, 'macintosh') || str_contains($ua, 'linux')) {
                $desktop++;
            } else {
                $other++;
            }
        }

        return [
            'desktop' => $desktop,
            'mobile'  => $mobile,
            'android' => $android,
            'ios'     => $ios,
            'other'   => $other,
        ];
    }
}
