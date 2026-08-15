<?php

namespace iEXPackages\Analytics\Services\Users;

use App\Models\User;
use App\Models\Task;
use iEXPackages\AuthAudit\Models\AuthEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UsersGlobalAnalyticsService
{
    /**
     * Общая сводка за период (агрегация напрямую по реальным таблицам)
     *
     * Период задаётся [from, to] включительно по дате (DATE(created_at)).
     * Все метрики завязаны на этот период.
     */
    public function getSummary(Carbon $from, Carbon $to): array
    {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        /*
         * 1) Новые пользователи за период (регистрации)
         */
        $newUsersCount = User::query()
            ->whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate])
            ->count();

        /*
         * 2) Активные пользователи (успешные входы в период, новая система AuthAudit)
         */
        $authUserIds = AuthEvent::query()
            ->where('event', '=', 'login_success')
            ->whereNotNull('user_id')
            ->whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate])
            ->pluck('user_id')
            ->filter()
            ->unique();

        $activeUsersCount = $authUserIds->count();

        /*
         * 3) Пользователи с заявками
         */
        $ordersUserIds = Task::query()
            ->whereNotNull('id_user')
            ->whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate])
            ->pluck('id_user')
            ->filter()
            ->unique();

        $usersWithOrdersCount = $ordersUserIds->count();

        /*
         * 4) Всего уникальных пользователей в периоде:
         *    те, кто или авторизовывался, или создавал заявки
         */
        $totalUsersInPeriod = $authUserIds
            ->merge($ordersUserIds)
            ->unique()
            ->count();

        /*
         * 5) Заявки по статусам
         *
         * ВАЖНО: подправь статусы под свои, если отличаются
         */
        $STATUS_SUCCESS  = 4;
        $STATUS_FAILED   = 5;
        $STATUS_CANCELED = 6;

        $ordersRow = Task::query()
            ->selectRaw('
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as successful_orders,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed_orders,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as canceled_orders
            ', [
                $STATUS_SUCCESS,
                $STATUS_FAILED,
                $STATUS_CANCELED,
            ])
            ->whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate])
            ->first();

        $totalOrders = (int) ($ordersRow->total_orders ?? 0);
        $successful  = (int) ($ordersRow->successful_orders ?? 0);
        $failed      = (int) ($ordersRow->failed_orders ?? 0);
        $canceled    = (int) ($ordersRow->canceled_orders ?? 0);

        /*
         * 6) Объём в USD (через order_exchange_totals)
         */
        $volumeRow = Task::query()
            ->leftJoin('order_exchange_totals', 'tasks.id', '=', 'order_exchange_totals.id_task')
            ->whereBetween(DB::raw('DATE(tasks.created_at)'), [$fromDate, $toDate])
            ->selectRaw('SUM(order_exchange_totals.exchange_usd) as total_volume_usd')
            ->first();

        $totalVolumeUsd = (float) ($volumeRow->total_volume_usd ?? 0.0);

        /*
         * 7) Прибыль в USD.
         * Если нет явного поля прибыли – пока 0.
         * Здесь можно подключить свою бизнес-логику.
         */
        $totalProfitUsd = 0.0;

        $safeUsers  = max(1, $totalUsersInPeriod);
        $safeOrders = max(1, $totalOrders);

        /*
         * 8) Дополнительные коэффициенты (масштабирование математики):
         *    - конверсия "пользователи с заявками" / "все пользователи периода"
         *    - заявок на одного пользователя
         *    - средний чек
         */
        $conversionUsersPercent = $totalUsersInPeriod > 0
            ? round($usersWithOrdersCount / $totalUsersInPeriod * 100, 2)
            : 0.0;

        $ordersPerUser    = $totalOrders / $safeUsers;
        $volumePerOrder   = $totalVolumeUsd / $safeOrders;
        $volumePerUser    = $totalVolumeUsd / $safeUsers;

        return [
            'period' => [
                'from' => $fromDate,
                'to'   => $toDate,
            ],
            'users' => [
                // Сколько уникальных пользователей было в периоде
                'total_users'           => $totalUsersInPeriod,
                // Зарегистрировались в периоде
                'new_users'             => $newUsersCount,
                // Авторизовывались в периоде
                'active_users_sum'      => $activeUsersCount,
                // Делали заявки в периоде
                'users_with_orders_sum' => $usersWithOrdersCount,
            ],
            'orders' => [
                'total_orders'      => $totalOrders,
                'successful_orders' => $successful,
                'failed_orders'     => $failed,
                'canceled_orders'   => $canceled,
            ],
            'money' => [
                'total_volume_usd'          => $totalVolumeUsd,
                'total_profit_usd'          => $totalProfitUsd,
                'avg_volume_per_user_total' => $volumePerUser,
                'avg_profit_per_user_total' => $totalProfitUsd / $safeUsers,
            ],
            'metrics' => [
                'conversion_users_with_orders_percent' => $conversionUsersPercent,
                'orders_per_user'                      => $ordersPerUser,
                'volume_per_order_usd'                 => $volumePerOrder,
            ],
        ];
    }

    /**
     * Изменения / проценты: текущий период vs предыдущий такой же длины.
     *
     * Массив изменений по пользователям, заявкам, деньгам.
     */
    public function getChanges(Carbon $from, Carbon $to): array
    {
        $days = $from->diffInDays($to) + 1;

        $prevTo   = (clone $from)->subDay();
        $prevFrom = (clone $prevTo)->subDays($days - 1);

        $current  = $this->getSummary($from, $to);
        $previous = $this->getSummary($prevFrom, $prevTo);

        return [
            'current_period'  => $current['period'],
            'previous_period' => $previous['period'],

            'users' => [
                'total_users' => [
                    'current'        => $current['users']['total_users'],
                    'previous'       => $previous['users']['total_users'],
                    'change_percent' => $this->percentChange(
                        $previous['users']['total_users'],
                        $current['users']['total_users']
                    ),
                ],
                'new_users' => [
                    'current'        => $current['users']['new_users'],
                    'previous'       => $previous['users']['new_users'],
                    'change_percent' => $this->percentChange(
                        $previous['users']['new_users'],
                        $current['users']['new_users']
                    ),
                ],
                'active_users_sum' => [
                    'current'        => $current['users']['active_users_sum'],
                    'previous'       => $previous['users']['active_users_sum'],
                    'change_percent' => $this->percentChange(
                        $previous['users']['active_users_sum'],
                        $current['users']['active_users_sum']
                    ),
                ],
                'users_with_orders_sum' => [
                    'current'        => $current['users']['users_with_orders_sum'],
                    'previous'       => $previous['users']['users_with_orders_sum'],
                    'change_percent' => $this->percentChange(
                        $previous['users']['users_with_orders_sum'],
                        $current['users']['users_with_orders_sum']
                    ),
                ],
            ],

            'orders' => [
                'total_orders' => [
                    'current'        => $current['orders']['total_orders'],
                    'previous'       => $previous['orders']['total_orders'],
                    'change_percent' => $this->percentChange(
                        $previous['orders']['total_orders'],
                        $current['orders']['total_orders']
                    ),
                ],
                'successful_orders' => [
                    'current'        => $current['orders']['successful_orders'],
                    'previous'       => $previous['orders']['successful_orders'],
                    'change_percent' => $this->percentChange(
                        $previous['orders']['successful_orders'],
                        $current['orders']['successful_orders']
                    ),
                ],
                'failed_orders' => [
                    'current'        => $current['orders']['failed_orders'],
                    'previous'       => $previous['orders']['failed_orders'],
                    'change_percent' => $this->percentChange(
                        $previous['orders']['failed_orders'],
                        $current['orders']['failed_orders']
                    ),
                ],
                'canceled_orders' => [
                    'current'        => $current['orders']['canceled_orders'],
                    'previous'       => $previous['orders']['canceled_orders'],
                    'change_percent' => $this->percentChange(
                        $previous['orders']['canceled_orders'],
                        $current['orders']['canceled_orders']
                    ),
                ],
            ],

            'money' => [
                'total_volume_usd' => [
                    'current'        => $current['money']['total_volume_usd'],
                    'previous'       => $previous['money']['total_volume_usd'],
                    'change_percent' => $this->percentChange(
                        $previous['money']['total_volume_usd'],
                        $current['money']['total_volume_usd']
                    ),
                ],
                'total_profit_usd' => [
                    'current'        => $current['money']['total_profit_usd'],
                    'previous'       => $previous['money']['total_profit_usd'],
                    'change_percent' => $this->percentChange(
                        $previous['money']['total_profit_usd'],
                        $current['money']['total_profit_usd']
                    ),
                ],
            ],
            'metrics' => [
                'conversion_users_with_orders_percent' => [
                    'current'  => $current['metrics']['conversion_users_with_orders_percent'] ?? 0,
                    'previous' => $previous['metrics']['conversion_users_with_orders_percent'] ?? 0,
                    'change_percent' => $this->percentChange(
                        $previous['metrics']['conversion_users_with_orders_percent'] ?? 0,
                        $current['metrics']['conversion_users_with_orders_percent'] ?? 0
                    ),
                ],
                'orders_per_user' => [
                    'current'  => $current['metrics']['orders_per_user'] ?? 0,
                    'previous' => $previous['metrics']['orders_per_user'] ?? 0,
                    'change_percent' => $this->percentChange(
                        $previous['metrics']['orders_per_user'] ?? 0,
                        $current['metrics']['orders_per_user'] ?? 0
                    ),
                ],
                'volume_per_order_usd' => [
                    'current'  => $current['metrics']['volume_per_order_usd'] ?? 0,
                    'previous' => $previous['metrics']['volume_per_order_usd'] ?? 0,
                    'change_percent' => $this->percentChange(
                        $previous['metrics']['volume_per_order_usd'] ?? 0,
                        $current['metrics']['volume_per_order_usd'] ?? 0
                    ),
                ],
            ],
        ];
    }

    /**
     * Новые пользователи и их активация (на основе users и tasks)
     */
    public function getNewUsersAnalytics(Carbon $from, Carbon $to): array
    {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        // Новые пользователи
        $newUsersCount = User::query()
            ->whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate])
            ->count();

        // Сколько новых за период сделали хотя бы 1 заявку
        $activatedUsers = Task::query()
            ->select('id_user')
            ->whereNotNull('id_user')
            ->whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate])
            ->distinct()
            ->pluck('id_user')
            ->filter()
            ->unique()
            ->count();

        return [
            'period' => [
                'from' => $fromDate,
                'to'   => $toDate,
            ],
            'new_users' => $newUsersCount,
            'activated_users' => $activatedUsers,
            'activation_rate_percent' => $newUsersCount > 0
                ? round($activatedUsers / $newUsersCount * 100, 2)
                : 0.0,
        ];
    }

    /**
     * Топ пользователей (максимум 30) по объёму в USD за период
     */
    public function getTopUsers(Carbon $from, Carbon $to, int $limit = 30): array
    {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        // Топ по объёму USD
        $topByVolume = Task::query()
            ->leftJoin('order_exchange_totals', 'tasks.id', '=', 'order_exchange_totals.id_task')
            ->selectRaw('tasks.id_user, SUM(order_exchange_totals.exchange_usd) as total_volume_usd, COUNT(*) as total_orders')
            ->whereNotNull('tasks.id_user')
            ->whereBetween(DB::raw('DATE(tasks.created_at)'), [$fromDate, $toDate])
            ->groupBy('tasks.id_user')
            ->orderByDesc('total_volume_usd')
            ->limit($limit)
            ->get();

        $userIds = $topByVolume->pluck('id_user')->filter()->unique();

        $users = User::query()
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        $top = $topByVolume->map(function ($row) use ($users) {
            $user = $users->get($row->id_user);

            return [
                'user_id'          => $row->id_user,
                'name'             => $user?->name,
                'email'            => $user?->email,
                'total_volume_usd' => (float) $row->total_volume_usd,
                'total_orders'     => (int) $row->total_orders,
            ];
        });

        return [
            'period' => [
                'from' => $fromDate,
                'to'   => $toDate,
            ],
            'limit'        => $limit,
            'top_by_volume'=> $top,
        ];
    }

    /**
     * Простая активность (для графика общей динамики по дням), на основе реальных таблиц.
     *
     * Можно сразу передавать на фронт для построения диаграмм.
     */
    public function getActivitySeries(Carbon $from, Carbon $to): array
    {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        // Новые пользователи по дням
        $newUsersRows = User::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as new_users')
            ->whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate])
            ->groupBy('date')
            ->pluck('new_users', 'date');

        // Заявки и объём по дням
        $ordersRows = Task::query()
            ->leftJoin('order_exchange_totals', 'tasks.id', '=', 'order_exchange_totals.id_task')
            ->selectRaw('
                DATE(tasks.created_at) as date,
                COUNT(*) as total_orders,
                SUM(order_exchange_totals.exchange_usd) as total_volume_usd
            ')
            ->whereBetween(DB::raw('DATE(tasks.created_at)'), [$fromDate, $toDate])
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $series = [
            'dates'            => [],
            'new_users'        => [],
            'total_orders'     => [],
            'total_volume_usd' => [],
        ];

        $cursor = $from->copy()->startOfDay();
        $end    = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $dateKey = $cursor->toDateString();

            $series['dates'][]            = $dateKey;
            $series['new_users'][]        = (int) ($newUsersRows[$dateKey] ?? 0);
            $series['total_orders'][]     = isset($ordersRows[$dateKey]) ? (int) $ordersRows[$dateKey]->total_orders : 0;
            $series['total_volume_usd'][] = isset($ordersRows[$dateKey]) ? (float) $ordersRows[$dateKey]->total_volume_usd : 0.0;

            $cursor->addDay();
        }

        return $series;
    }

    /**
     * RFM-сегментация пользователей за период
     */
    public function getRfmSegments(Carbon $from, Carbon $to, array $volumeThresholds = [1000, 10000], array $frequencyThresholds = [3, 10]): array
    {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        // Берём только пользователей, у которых были заявки в период
        $rows = Task::query()
            ->leftJoin('order_exchange_totals', 'tasks.id', '=', 'order_exchange_totals.id_task')
            ->selectRaw('
                tasks.id_user,
                COUNT(*) as orders_count,
                SUM(order_exchange_totals.exchange_usd) as volume_usd,
                MAX(tasks.created_at) as last_order_at
            ')
            ->whereNotNull('tasks.id_user')
            ->whereBetween(DB::raw('DATE(tasks.created_at)'), [$fromDate, $toDate])
            ->groupBy('tasks.id_user')
            ->get();

        $segments = [
            'vip'       => [],
            'active'    => [],
            'warm'      => [],
            'cold'      => [],
        ];

        foreach ($rows as $row) {
            $lastOrderAt = Carbon::parse($row->last_order_at);
            $recencyDays = $lastOrderAt->diffInDays($to);

            $frequency = (int) $row->orders_count;
            $volume    = (float) $row->volume_usd;

            // Простая RFM-сегментация
            $segmentKey = 'cold';

            if ($recencyDays <= 7 && $frequency >= $frequencyThresholds[1] && $volume >= $volumeThresholds[1]) {
                $segmentKey = 'vip';
            } elseif ($recencyDays <= 30 && $frequency >= $frequencyThresholds[0] && $volume >= $volumeThresholds[0]) {
                $segmentKey = 'active';
            } elseif ($recencyDays <= 90) {
                $segmentKey = 'warm';
            }

            $segments[$segmentKey][] = [
                'id_user'      => (int) $row->id_user,
                'orders_count' => $frequency,
                'volume_usd'   => $volume,
                'last_order_at'=> $lastOrderAt,
                'recency_days' => $recencyDays,
            ];
        }

        // Агрегированная статистика по сегментам
        $summary = [];
        foreach ($segments as $key => $users) {
            $count = count($users);
            $totalVolume = array_sum(array_column($users, 'volume_usd'));
            $totalOrders = array_sum(array_column($users, 'orders_count'));

            $summary[$key] = [
                'users_count'  => $count,
                'total_volume' => $totalVolume,
                'total_orders' => $totalOrders,
                'avg_volume'   => $count > 0 ? $totalVolume / $count : 0,
            ];
        }

        return [
            'period'   => ['from' => $fromDate, 'to' => $toDate],
            'segments' => $segments,
            'summary'  => $summary,
        ];
    }

    /**
     * Когортный анализ по месяцу регистрации и удержанию в текущем периоде
     */
    public function getCohortsRetention(Carbon $from, Carbon $to): array
    {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        // Находим когорты по месяцу регистрации (YYYY-MM)
        $cohortRows = User::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as cohort_key, COUNT(*) as total_users")
            ->groupBy('cohort_key')
            ->get()
            ->keyBy('cohort_key');

        // Активность (заявки) по когортам в текущем периоде
        $activeRows = Task::query()
            ->join('users', 'tasks.id_user', '=', 'users.id')
            ->selectRaw("DATE_FORMAT(users.created_at, '%Y-%m') as cohort_key, COUNT(DISTINCT tasks.id_user) as active_users")
            ->whereBetween(DB::raw('DATE(tasks.created_at)'), [$fromDate, $toDate])
            ->groupBy('cohort_key')
            ->get()
            ->keyBy('cohort_key');

        $cohorts = [];

        foreach ($cohortRows as $key => $row) {
            $totalUsers   = (int) $row->total_users;
            $activeUsers  = isset($activeRows[$key]) ? (int) $activeRows[$key]->active_users : 0;
            $retentionPct = $totalUsers > 0 ? round($activeUsers / $totalUsers * 100, 2) : 0.0;

            $cohorts[] = [
                'cohort_key'      => $key,
                'total_users'     => $totalUsers,
                'active_users'    => $activeUsers,
                'retention_percent' => $retentionPct,
            ];
        }

        return [
            'period'  => ['from' => $fromDate, 'to' => $toDate],
            'cohorts' => $cohorts,
        ];
    }

    /**
     * Воронка: регистрация → активация → повтор → VIP
     */
    public function getFunnel(Carbon $from, Carbon $to, float $vipVolumeThreshold = 10000.0): array
    {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        // Зарегистрировались в период
        $usersQuery = User::query()
            ->whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate]);

        $registeredIds = $usersQuery
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $registeredCount = count($registeredIds);

        if ($registeredCount === 0) {
            return [
                'period' => ['from' => $fromDate, 'to' => $toDate],
                'steps'  => [],
            ];
        }

        // Активированные: сделали хотя бы одну заявку в период
        $activated = Task::query()
            ->whereIn('id_user', $registeredIds)
            ->whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate])
            ->selectRaw('id_user, COUNT(*) as orders_count')
            ->groupBy('id_user')
            ->get();

        $activatedIds = $activated->pluck('id_user')->map(fn ($id) => (int) $id)->all();
        $activatedCount = count($activatedIds);

        // Повторные: 2+ заявок в период
        $repeatCount = $activated->filter(fn ($row) => (int) $row->orders_count >= 2)->count();

        // VIP: суммарный объём за период >= порога
        $vipRows = Task::query()
            ->whereIn('tasks.id_user', $registeredIds)
            ->whereBetween(DB::raw('DATE(tasks.created_at)'), [$fromDate, $toDate])
            ->leftJoin('order_exchange_totals', 'tasks.id', '=', 'order_exchange_totals.id_task')
            ->selectRaw('tasks.id_user, SUM(order_exchange_totals.exchange_usd) as volume_usd')
            ->groupBy('tasks.id_user')
            ->having('volume_usd', '>=', $vipVolumeThreshold)
            ->get();

        $vipCount = $vipRows->count();

        $steps = [
            [
                'key'           => 'registered',
                'label'         => 'Зарегистрировались',
                'count'         => $registeredCount,
                'conversion_from_prev_percent' => 100.0,
            ],
            [
                'key'           => 'activated',
                'label'         => 'Сделали первую заявку',
                'count'         => $activatedCount,
                'conversion_from_prev_percent' => $registeredCount > 0 ? round($activatedCount / $registeredCount * 100, 2) : 0.0,
            ],
            [
                'key'           => 'repeat',
                'label'         => 'Повторные заявки (2+)',
                'count'         => $repeatCount,
                'conversion_from_prev_percent' => $activatedCount > 0 ? round($repeatCount / $activatedCount * 100, 2) : 0.0,
            ],
            [
                'key'           => 'vip',
                'label'         => 'VIP (по объёму)',
                'count'         => $vipCount,
                'conversion_from_prev_percent' => $repeatCount > 0 ? round($vipCount / $repeatCount * 100, 2) : 0.0,
            ],
        ];

        return [
            'period' => ['from' => $fromDate, 'to' => $toDate],
            'steps'  => $steps,
        ];
    }

    /**
     * Расширенная аналитика по топовым пользователям
     */
    public function getTopUsersExtended(Carbon $from, Carbon $to, int $limit = 30): array
    {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        $rows = Task::query()
            ->leftJoin('order_exchange_totals', 'tasks.id', '=', 'order_exchange_totals.id_task')
            ->selectRaw('
                tasks.id_user,
                COUNT(*) as orders_count,
                SUM(order_exchange_totals.exchange_usd) as volume_usd,
                MIN(tasks.created_at) as first_order_at,
                MAX(tasks.created_at) as last_order_at
            ')
            ->whereNotNull('tasks.id_user')
            ->whereBetween(DB::raw('DATE(tasks.created_at)'), [$fromDate, $toDate])
            ->groupBy('tasks.id_user')
            ->orderByDesc('volume_usd')
            ->limit($limit)
            ->get();

        $userIds = $rows->pluck('id_user')->filter()->unique()->all();

        $users = User::query()
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        $data = [];

        foreach ($rows as $row) {
            $user      = $users->get($row->id_user);
            $firstAt   = Carbon::parse($row->first_order_at);
            $lastAt    = Carbon::parse($row->last_order_at);
            $recency   = $lastAt->diffInDays($to);
            $frequency = (int) $row->orders_count;
            $volume    = (float) $row->volume_usd;
            $avgOrder  = $frequency > 0 ? $volume / $frequency : 0.0;

            $segment = 'regular';
            if ($recency <= 7 && $volume >= 10000) {
                $segment = 'vip';
            } elseif ($recency <= 30 && $volume >= 1000) {
                $segment = 'active';
            } elseif ($recency > 90) {
                $segment = 'churn_risk';
            }

            $data[] = [
                'id_user'    => (int) $row->id_user,
                'name'       => $user?->name,
                'email'      => $user?->email,
                'orders_count' => $frequency,
                'volume_usd'   => $volume,
                'avg_order_usd'=> $avgOrder,
                'first_order_at' => $firstAt,
                'last_order_at'  => $lastAt,
                'recency_days'   => $recency,
                'segment'        => $segment,
            ];
        }

        return [
            'period' => ['from' => $fromDate, 'to' => $toDate],
            'items'  => $data,
        ];
    }

    /**
     * Рост/падение по пользователям (динамика)
     */
    public function getUsersDynamics(Carbon $from, Carbon $to, float $minVolumeUsd = 0.0, int $minOrders = 1, float $thresholdPercent = 50.0): array
    {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        // Предыдущий период такой же длины
        $days = $from->diffInDays($to) + 1;
        $prevTo   = (clone $from)->subDay();
        $prevFrom = (clone $prevTo)->subDays($days - 1);

        $prevFromDate = $prevFrom->toDateString();
        $prevToDate   = $prevTo->toDateString();

        // Текущий период: объём и заявки по пользователям
        $currentRows = Task::query()
            ->leftJoin('order_exchange_totals', 'tasks.id', '=', 'order_exchange_totals.id_task')
            ->selectRaw('
                tasks.id_user,
                COUNT(*) as orders_count,
                SUM(order_exchange_totals.exchange_usd) as volume_usd
            ')
            ->whereNotNull('tasks.id_user')
            ->whereBetween(DB::raw('DATE(tasks.created_at)'), [$fromDate, $toDate])
            ->groupBy('tasks.id_user')
            ->get()
            ->keyBy('id_user');

        // Предыдущий период
        $previousRows = Task::query()
            ->leftJoin('order_exchange_totals', 'tasks.id', '=', 'order_exchange_totals.id_task')
            ->selectRaw('
                tasks.id_user,
                COUNT(*) as orders_count,
                SUM(order_exchange_totals.exchange_usd) as volume_usd
            ')
            ->whereNotNull('tasks.id_user')
            ->whereBetween(DB::raw('DATE(tasks.created_at)'), [$prevFromDate, $prevToDate])
            ->groupBy('tasks.id_user')
            ->get()
            ->keyBy('id_user');

        $userIds = $currentRows->keys()
            ->merge($previousRows->keys())
            ->filter()
            ->unique()
            ->all();

        $gainers = [];
        $losers  = [];

        foreach ($userIds as $idUser) {
            $curr = $currentRows->get($idUser);
            $prev = $previousRows->get($idUser);

            $currVolume = $curr ? (float) $curr->volume_usd : 0.0;
            $prevVolume = $prev ? (float) $prev->volume_usd : 0.0;

            $currOrders = $curr ? (int) $curr->orders_count : 0;
            $prevOrders = $prev ? (int) $prev->orders_count : 0;

            // Фильтр по минимальным значениям
            if (
                max($currVolume, $prevVolume) < $minVolumeUsd &&
                max($currOrders, $prevOrders) < $minOrders
            ) {
                continue;
            }

            // Если совсем нет движения — пропускаем
            if ($currVolume === 0.0 && $prevVolume === 0.0) {
                continue;
            }

            $changePercent = $this->percentChange($prevVolume, $currVolume);

            $row = [
                'id_user'        => (int) $idUser,
                'volume_current' => $currVolume,
                'volume_previous'=> $prevVolume,
                'orders_current' => $currOrders,
                'orders_previous'=> $prevOrders,
                'change_percent' => $changePercent,
            ];

            if ($changePercent >= $thresholdPercent) {
                $gainers[] = $row;
            } elseif ($changePercent <= -$thresholdPercent) {
                $losers[] = $row;
            }
        }

        // Сортируем: растущие по убыванию % роста, падающие по возрастанию (больше падение – выше)
        usort($gainers, fn ($a, $b) => $b['change_percent'] <=> $a['change_percent']);
        usort($losers, fn ($a, $b) => $a['change_percent'] <=> $b['change_percent']);

        return [
            'period' => [
                'current'  => ['from' => $fromDate, 'to' => $toDate],
                'previous' => ['from' => $prevFromDate, 'to' => $prevToDate],
            ],
            'gainers' => $gainers,
            'losers'  => $losers,
        ];
    }

    /**
     * Риск-фокус / подозрительные пользователи
     */
    public function getRiskUsers(Carbon $from, Carbon $to, float $minVolumeUsd = 10000.0, int $minDistinctIps = 3, int $minDistinctDevices = 2, int $minRiskScore = 40): array
    {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        // Логины из новой системы аудита (AuthEvent)
        $authRows = AuthEvent::query()
            ->selectRaw('
                user_id,
                COUNT(*) as logins_count,
                COUNT(DISTINCT ip) as distinct_ips,
                COUNT(DISTINCT device_id) as distinct_devices
            ')
            ->where('event', '=', 'login_success')
            ->whereNotNull('user_id')
            ->whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate])
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        // Объём заявок по пользователям
        $volumeRows = Task::query()
            ->leftJoin('order_exchange_totals', 'tasks.id', '=', 'order_exchange_totals.id_task')
            ->selectRaw('
                tasks.id_user,
                SUM(order_exchange_totals.exchange_usd) as volume_usd,
                COUNT(*) as orders_count
            ')
            ->whereNotNull('tasks.id_user')
            ->whereBetween(DB::raw('DATE(tasks.created_at)'), [$fromDate, $toDate])
            ->groupBy('tasks.id_user')
            ->get()
            ->keyBy('id_user');

        $userIds = $authRows->keys()
            ->merge($volumeRows->keys())
            ->filter()
            ->unique()
            ->all();

        $users = User::query()
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        $items = [];

        foreach ($userIds as $idUser) {
            $auth   = $authRows->get($idUser);
            $volume = $volumeRows->get($idUser);
            $user   = $users->get($idUser);

            $loginsCount      = $auth ? (int) $auth->logins_count : 0;
            $distinctIps      = $auth ? (int) $auth->distinct_ips : 0;
            $distinctDevices  = $auth ? (int) $auth->distinct_devices : 0;
            $volumeUsd        = $volume ? (float) $volume->volume_usd : 0.0;
            $ordersCount      = $volume ? (int) $volume->orders_count : 0;

            // Простейшая модель риска
            $riskScore = 0;
            $reasons   = [];

            if ($volumeUsd >= $minVolumeUsd) {
                $riskScore += 40;
                $reasons[] = 'high_volume';
            }

            if ($distinctIps >= $minDistinctIps) {
                $riskScore += 25;
                $reasons[] = 'many_ips';
            }

            if ($distinctDevices >= $minDistinctDevices) {
                $riskScore += 20;
                $reasons[] = 'many_devices';
            }

            if ($user && empty($user->google2fa_secret)) {
                $riskScore += 15;
                $reasons[] = 'no_2fa';
            }

            if ($riskScore < $minRiskScore) {
                continue;
            }

            $items[] = [
                'id_user'         => (int) $idUser,
                'name'            => $user?->name,
                'email'           => $user?->email,
                'risk_score'      => $riskScore,
                'reasons'         => $reasons,
                'logins_count'    => $loginsCount,
                'distinct_ips'    => $distinctIps,
                'distinct_devices'=> $distinctDevices,
                'volume_usd'      => $volumeUsd,
                'orders_count'    => $ordersCount,
            ];
        }

        // Сортируем по убыванию риска
        usort($items, fn ($a, $b) => $b['risk_score'] <=> $a['risk_score']);

        return [
            'period' => [
                'from' => $fromDate,
                'to'   => $toDate,
            ],
            'items'  => $items,
        ];
    }

    /**
     * Аналитика LTV (ценность пользователя) за период
     */
    public function getLtvAnalytics(Carbon $from, Carbon $to): array
    {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        // LTV по пользователям за период: суммарный объём и количество заявок
        $rows = Task::query()
            ->leftJoin('order_exchange_totals', 'tasks.id', '=', 'order_exchange_totals.id_task')
            ->selectRaw('
                tasks.id_user,
                COUNT(*) as orders_count,
                SUM(order_exchange_totals.exchange_usd) as volume_usd
            ')
            ->whereNotNull('tasks.id_user')
            ->whereBetween(DB::raw('DATE(tasks.created_at)'), [$fromDate, $toDate])
            ->groupBy('tasks.id_user')
            ->get();

        $ltvs = [];
        $sumLtv = 0.0;
        $maxLtv = 0.0;
        $countUsers = 0;

        $buckets = [
            '0_100'       => 0,
            '100_1000'    => 0,
            '1000_10000'  => 0,
            '10000_plus'  => 0,
        ];

        foreach ($rows as $row) {
            $userId = (int) $row->id_user;
            $volume = (float) $row->volume_usd;
            $orders = (int) $row->orders_count;
            $avgOrder = $orders > 0 ? $volume / $orders : 0.0;

            $ltvs[] = [
                'id_user'       => $userId,
                'volume_usd'    => $volume,
                'orders_count'  => $orders,
                'avg_order_usd' => $avgOrder,
            ];

            $sumLtv += $volume;
            $maxLtv = max($maxLtv, $volume);
            $countUsers++;

            if ($volume < 100) {
                $buckets['0_100']++;
            } elseif ($volume < 1000) {
                $buckets['100_1000']++;
            } elseif ($volume < 10000) {
                $buckets['1000_10000']++;
            } else {
                $buckets['10000_plus']++;
            }
        }

        // Считаем средний LTV и медиану
        $avgLtv = $countUsers > 0 ? $sumLtv / $countUsers : 0.0;
        $medianLtv = 0.0;
        if ($countUsers > 0) {
            $values = array_column($ltvs, 'volume_usd');
            sort($values);
            $mid = intdiv($countUsers, 2);
            if ($countUsers % 2 === 1) {
                $medianLtv = $values[$mid];
            } else {
                $medianLtv = ($values[$mid - 1] + $values[$mid]) / 2;
            }
        }

        // Для UI не обязательно отдавать всех пользователей, ограничим топ-N
        usort($ltvs, fn ($a, $b) => $b['volume_usd'] <=> $a['volume_usd']);
        $topUsers = array_slice($ltvs, 0, 50);

        return [
            'period' => [
                'from' => $fromDate,
                'to'   => $toDate,
            ],
            'summary' => [
                'users_with_ltv'   => $countUsers,
                'total_ltv_usd'    => $sumLtv,
                'avg_ltv_usd'      => $avgLtv,
                'median_ltv_usd'   => $medianLtv,
                'max_ltv_usd'      => $maxLtv,
                'buckets'          => $buckets,
            ],
            'top_users' => $topUsers,
        ];
    }

    /**
     * Аналитика поведения пользователей: heatmap, частота
     */
    public function getBehaviorAnalytics(Carbon $from, Carbon $to): array
    {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        // Heatmap по дням недели и часам
        $heatmapRows = Task::query()
            ->selectRaw('
                DAYOFWEEK(created_at) as dow,
                HOUR(created_at) as hour,
                COUNT(*) as orders_count
            ')
            ->whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate])
            ->groupBy('dow', 'hour')
            ->get()
            ->map(function ($row) {
                return [
                    'dow'          => (int) $row->dow,
                    'hour'         => (int) $row->hour,
                    'orders_count' => (int) $row->orders_count,
                ];
            })
            ->all();

        // Частота заявок на пользователя
        $freqRows = Task::query()
            ->selectRaw('id_user, COUNT(*) as orders_count')
            ->whereNotNull('id_user')
            ->whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate])
            ->groupBy('id_user')
            ->get();

        $buckets = [
            'one_time'       => 0,
            'few_2_5'        => 0,
            'regular_6_20'   => 0,
            'heavy_21_plus'  => 0,
        ];

        foreach ($freqRows as $row) {
            $count = (int) $row->orders_count;
            if ($count === 1) {
                $buckets['one_time']++;
            } elseif ($count <= 5) {
                $buckets['few_2_5']++;
            } elseif ($count <= 20) {
                $buckets['regular_6_20']++;
            } else {
                $buckets['heavy_21_plus']++;
            }
        }

        return [
            'period' => [
                'from' => $fromDate,
                'to'   => $toDate,
            ],
            'heatmap' => $heatmapRows,
            'frequency' => [
                'buckets'      => $buckets,
                'total_users'  => count($freqRows),
            ],
        ];
    }

    /**
     * Пути жизни пользователя (lifetime paths)
     */
    public function getLifetimePaths(Carbon $from, Carbon $to, int $newDays = 30, int $inactiveDays = 60): array
    {
        // lifetime считаем по всей истории до $to
        $asOf = $to->copy()->endOfDay();

        // Все пользователи
        $users = User::query()
            ->select('id', 'created_at')
            ->get()
            ->keyBy('id');

        if ($users->isEmpty()) {
            return [
                'as_of'       => $asOf->toDateString(),
                'params'      => ['new_days' => $newDays, 'inactive_days' => $inactiveDays],
                'paths'       => [],
                'total_users' => 0,
            ];
        }

        // Агрегаты по заявкам до asOf
        $taskAgg = Task::query()
            ->selectRaw('
                id_user,
                COUNT(*) as orders_count,
                MIN(created_at) as first_order_at,
                MAX(created_at) as last_order_at
            ')
            ->whereNotNull('id_user')
            ->where('created_at', '<=', $asOf->toDateTimeString())
            ->groupBy('id_user')
            ->get()
            ->keyBy('id_user');

        $paths = [
            'new_no_orders'    => 0,
            'never_activated'  => 0,
            'new_one_order'    => 0,
            'one_order_recent' => 0,
            'one_order_old'    => 0,
            'loyal'            => 0,
            'churned'          => 0,
        ];

        foreach ($users as $id => $user) {
            /** @var \Carbon\Carbon $regAt */
            $regAt = Carbon::parse($user->created_at);
            $daysSinceReg = $regAt->diffInDays($asOf);

            $agg = $taskAgg->get($id);

            if (!$agg) {
                if ($daysSinceReg <= $newDays) {
                    $paths['new_no_orders']++;
                } else {
                    $paths['never_activated']++;
                }
                continue;
            }

            $ordersCount = (int) $agg->orders_count;
            $lastOrderAt = Carbon::parse($agg->last_order_at);
            $recencyDays = $lastOrderAt->diffInDays($asOf);

            if ($ordersCount === 1) {
                if ($daysSinceReg <= $newDays) {
                    $paths['new_one_order']++;
                } elseif ($recencyDays <= $inactiveDays) {
                    $paths['one_order_recent']++;
                } else {
                    $paths['one_order_old']++;
                }
            } else {
                if ($recencyDays <= $inactiveDays) {
                    $paths['loyal']++;
                } else {
                    $paths['churned']++;
                }
            }
        }

        return [
            'as_of'       => $asOf->toDateString(),
            'params'      => ['new_days' => $newDays, 'inactive_days' => $inactiveDays],
            'paths'       => $paths,
            'total_users' => $users->count(),
        ];
    }

    /**
     * Сводный User Importance Score — оценка важности пользователя для системы
     *
     * Основывается на:
     *  - обороте (volume_usd) за период,
     *  - количестве заявок (orders_count),
     *  - давности последней заявки (recency_days).
     *
     * Возвращает список пользователей с importance_score от 0 до 100.
     */
    public function getImportanceAnalytics(Carbon $from, Carbon $to, int $limit = 100): array
    {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        // Собираем агрегаты по пользователям за период с данными пользователя
        $rows = Task::query()
            ->leftJoin('order_exchange_totals', 'tasks.id', '=', 'order_exchange_totals.id_task')
            ->leftJoin('users', 'tasks.id_user', '=', 'users.id')
            ->selectRaw('
                tasks.id_user,
                users.name as user_name,
                users.email as user_email,
                COUNT(*) as orders_count,
                SUM(order_exchange_totals.exchange_usd) as volume_usd,
                MIN(tasks.created_at) as first_order_at,
                MAX(tasks.created_at) as last_order_at
            ')
            ->whereNotNull('tasks.id_user')
            ->whereBetween(DB::raw('DATE(tasks.created_at)'), [$fromDate, $toDate])
            ->groupBy('tasks.id_user', 'users.name', 'users.email')
            ->get();

        if ($rows->isEmpty()) {
            return [
                'period' => [
                    'from' => $fromDate,
                    'to'   => $toDate,
                ],
                'summary' => [
                    'users_count'    => 0,
                    'avg_importance' => 0.0,
                    'max_importance' => 0.0,
                ],
                'items' => [],
            ];
        }

        $items        = [];
        $maxVolume    = 0.0;
        $maxFreq      = 0;
        $sumImportance = 0.0;
        $maxImportance = 0.0;
        $now          = $to->copy();

        // Находим максимальные значения для нормализации
        foreach ($rows as $row) {
            $volume = (float) $row->volume_usd;
            $freq   = (int) $row->orders_count;

            $maxVolume = max($maxVolume, $volume);
            $maxFreq   = max($maxFreq, $freq);
        }

        // Считаем importance score для каждого пользователя
        foreach ($rows as $row) {
            $userId   = (int) $row->id_user;
            $volume   = (float) $row->volume_usd;
            $freq     = (int) $row->orders_count;
            $lastAt   = Carbon::parse($row->last_order_at);
            $recency  = $lastAt->diffInDays($now);

            $volumeScore  = $maxVolume > 0 ? $volume / $maxVolume : 0.0;
            $freqScore    = $maxFreq > 0 ? $freq / $maxFreq : 0.0;
            // Свежесть: чем меньше recency, тем ближе к 1; считаем до 90 дней
            $recencyScore = $recency === 0 ? 1.0 : max(0.0, 1 - min($recency / 90, 1));

            // 60% объём, 25% частота, 15% свежесть
            $score          = 0.6 * $volumeScore + 0.25 * $freqScore + 0.15 * $recencyScore;
            $importanceScore = round($score * 100, 2);

            $sumImportance += $importanceScore;
            $maxImportance  = max($maxImportance, $importanceScore);

            $items[] = [
                'id_user'         => $userId,
                'name'            => $row->user_name,
                'email'           => $row->user_email,
                'orders_count'    => $freq,
                'volume_usd'      => $volume,
                'avg_order_usd'   => $freq > 0 ? $volume / $freq : 0.0,
                'first_order_at'  => $row->first_order_at,
                'last_order_at'   => $row->last_order_at,
                'recency_days'    => $recency,
                'importance_score'=> $importanceScore,
            ];
        }

        // Сортируем пользователей по importance_score
        usort($items, static function (array $a, array $b): int {
            return $b['importance_score'] <=> $a['importance_score'];
        });

        $usersCount    = count($items);
        $avgImportance = $usersCount > 0 ? $sumImportance / $usersCount : 0.0;

        // Ограничиваем топ-N для UI
        $topItems = array_slice($items, 0, $limit);

        return [
            'period' => [
                'from' => $fromDate,
                'to'   => $toDate,
            ],
            'summary' => [
                'users_count'    => $usersCount,
                'avg_importance' => $avgImportance,
                'max_importance' => $maxImportance,
            ],
            'items' => $topItems,
        ];
    }

    public function getDeviceAnalytics(Carbon $from, Carbon $to): array
    {
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        // Активные пользователи за период = успешные логины (login_success) из новой системы аудита
        $authUserIds = AuthEvent::query()
            ->where('event', '=', 'login_success')
            ->whereNotNull('user_id')
            ->whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate])
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        // Реальное количество успешных входов (не уникальных пользователей)
        $totalLogins = AuthEvent::query()
            ->where('event', '=', 'login_success')
            ->whereNotNull('user_id')
            ->whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate])
            ->count();

        if (empty($authUserIds)) {
            return [
                'period' => [
                    'from' => $fromDate,
                    'to'   => $toDate,
                ],
                'devices' => [],
                'summary' => [
                    'total_users'      => 0,
                    'total_logins'     => 0,
                ],
            ];
        }

        // Пользователи по типу устройства (из users.user_device)
        $userRows = User::query()
            ->selectRaw('COALESCE(user_device, "unknown") as device_type, COUNT(*) as users_count')
            ->whereIn('id', $authUserIds)
            ->groupBy('device_type')
            ->get();

        $devices = [];
        $totalUsers = 0;

        foreach ($userRows as $row) {
            $usersCount = (int) $row->users_count;
            $devices[] = [
                'device_type'         => $row->device_type,
                'users_count'         => $usersCount,
                'users_share_percent' => 0, // пересчитаем ниже
            ];
            $totalUsers += $usersCount;
        }

        foreach ($devices as &$device) {
            $device['users_share_percent'] = $totalUsers > 0
                ? round($device['users_count'] / $totalUsers * 100, 2)
                : 0.0;
        }
        unset($device);

        return [
            'period' => [
                'from' => $fromDate,
                'to'   => $toDate,
            ],
            'devices' => $devices,
            'summary' => [
                'total_users'  => $totalUsers,
                'total_logins' => (int) $totalLogins,
                'unique_users' => count($authUserIds),
            ],
        ];
    }

    protected function percentChange(float|int $old, float|int $new): float
    {
        if ($old == 0) {
            return $new > 0 ? 100.0 : 0.0;
        }

        return round(($new - $old) / $old * 100, 2);
    }
}
