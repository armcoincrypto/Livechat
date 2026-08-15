<?php

namespace iEXPackages\Analytics\Services\Exchanges;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DirectionsAnalyticsService
{
    /**
     * Основная аналитика направлений за период.
     */
    public function getDirectionsStats(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->startOfMonth();
        $to   = $to   ?? now()->endOfMonth();

        $directionsTable = 'direction_exchange';
        $statsTable      = 'direction_exchange_stats_daily';

        // ВАЖНО:
        // - Используем DB::table, чтобы SoftDeletes не отфильтровал удалённые направления
        // - Явно выбираем deleted_at и status
        $rows = DB::table($directionsTable . ' as d')
            ->leftJoin($statsTable . ' as s', function ($join) use ($from, $to) {
                $join->on('s.direction_exchange_id', '=', 'd.id')
                    ->whereBetween('s.stat_date', [$from->toDateString(), $to->toDateString()]);
            })
            ->selectRaw('
                d.id,
                d.tech_name,
                d.status,
                d.deleted_at,
                COALESCE(SUM(s.total_orders), 0)          as total_orders,
                COALESCE(SUM(s.completed_orders), 0)      as completed_orders,
                COALESCE(SUM(s.rejected_orders), 0)       as rejected_orders,
                COALESCE(SUM(s.cancelled_orders), 0)      as cancelled_orders,
                COALESCE(SUM(s.processing_orders), 0)     as processing_orders,
                COALESCE(SUM(s.total_profit_usd), 0)      as total_profit_usd,
                COALESCE(SUM(s.total_amount_from_usd), 0) as total_amount_from_usd,
                COALESCE(SUM(s.total_amount_to_usd), 0)   as total_amount_to_usd
            ')
            ->groupBy('d.id', 'd.tech_name', 'd.status', 'd.deleted_at')
            ->get();

        $totalOrdersAll = (int) $rows->sum('total_orders');

        $items = $rows->map(function ($row) use ($totalOrdersAll) {
            $total     = (int) $row->total_orders;
            $completed = (int) $row->completed_orders;
            $rejected  = (int) $row->rejected_orders;
            $cancelled = (int) $row->cancelled_orders;

            $processed = $completed + $rejected + $cancelled;

            $sharePercent = $totalOrdersAll > 0
                ? round($total * 100 / $totalOrdersAll, 2)
                : 0.0;

            $successRate = $processed > 0
                ? round($completed * 100 / $processed, 2)
                : 0.0;

            $rejectRate = $processed > 0
                ? round($rejected * 100 / $processed, 2)
                : 0.0;

            $cancelRate = $processed > 0
                ? round($cancelled * 100 / $processed, 2)
                : 0.0;

            $category = $this->detectCategory(
                total: $total,
                sharePercent: $sharePercent,
                successRate: $successRate,
                rejectRate: $rejectRate
            );

            return [
                'id'                    => (int) $row->id,
                'tech_name'             => $row->tech_name,
                'name'                  => null, // column `name` отсутствует в direction_exchange, для отображения используем tech_name
                'status'                => (int) $row->status,
                'is_deleted'            => $row->deleted_at !== null,

                'total_orders'          => $total,
                'completed_orders'      => $completed,
                'rejected_orders'       => $rejected,
                'cancelled_orders'      => $cancelled,
                'processing_orders'     => (int) $row->processing_orders,

                'share_percent'         => $sharePercent,
                'success_rate'          => $successRate,
                'reject_rate'           => $rejectRate,
                'cancel_rate'           => $cancelRate,

                'total_profit_usd'      => (string) $row->total_profit_usd,
                'total_amount_from_usd' => (string) $row->total_amount_from_usd,
                'total_amount_to_usd'   => (string) $row->total_amount_to_usd,

                'category'              => $category,
            ];
        });

        $popular = $items
            ->filter(fn ($i) => $i['category'] === 'popular')
            ->sortByDesc('total_orders')
            ->values();

        $noDemand = $items
            ->filter(fn ($i) => $i['total_orders'] === 0)
            ->values();

        $problem = $items
            ->filter(fn ($i) => $i['category'] === 'problem')
            ->sortByDesc('reject_rate')
            ->values();

        $lowProfit = $items
            ->filter(fn ($i) => $i['total_orders'] > 0 && (float) $i['total_profit_usd'] <= 0)
            ->values();

        $highProfit = $items
            ->filter(fn ($i) => (float) $i['total_profit_usd'] > 0 && $i['total_orders'] > 0)
            ->sortByDesc(fn ($i) => (float) $i['total_profit_usd'])
            ->values();

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],

            'summary' => [
                'total_directions'          => $items->count(),
                'directions_with_orders'    => $items->where('total_orders', '>', 0)->count(),
                'directions_without_orders' => $items->where('total_orders', '=', 0)->count(),
                'total_orders'              => $totalOrdersAll,
            ],

            // Сегменты для виджетов (короткие подборки, тяжёлые списки идут через отдельные пагинированные роуты)
            'segments' => [
                'top_popular' => $popular->take(10)->all(),
                // Для дашборда отдаём только ограниченный список направлений без спроса.
                // Полный список доступен через отдельный пагинированный эндпоинт noDemand().
                'no_demand'   => $noDemand->take(10)->all(),
                'problem'     => $problem->take(10)->all(),
                'low_profit'  => $lowProfit->take(10)->all(),
                'high_profit' => $highProfit->take(10)->all(),
            ],
        ];
    }

    /**
     * Пагинация по всем направлениям за период.
     */
    public function getAllDirectionsPaginated(
        ?Carbon $from = null,
        ?Carbon $to = null,
        int $perPage = 30,
        ?string $search = null
    ): LengthAwarePaginator {
        $from = $from ?? now()->startOfMonth();
        $to   = $to   ?? now()->endOfMonth();

        $directionsTable = 'direction_exchange';
        $statsTable      = 'direction_exchange_stats_daily';

        // Общий объём заявок по всем направлениям за период (для расчёта share_percent)
        $totalOrdersAll = (int) DB::table($statsTable)
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->sum('total_orders');

        $query = DB::table($directionsTable . ' as d')
            ->leftJoin($statsTable . ' as s', function ($join) use ($from, $to) {
                $join->on('s.direction_exchange_id', '=', 'd.id')
                    ->whereBetween('s.stat_date', [$from->toDateString(), $to->toDateString()]);
            })
            ->selectRaw('
                d.id,
                d.tech_name,
                d.status,
                d.deleted_at,
                COALESCE(SUM(s.total_orders), 0)          as total_orders,
                COALESCE(SUM(s.completed_orders), 0)      as completed_orders,
                COALESCE(SUM(s.rejected_orders), 0)       as rejected_orders,
                COALESCE(SUM(s.cancelled_orders), 0)      as cancelled_orders,
                COALESCE(SUM(s.processing_orders), 0)     as processing_orders,
                COALESCE(SUM(s.total_profit_usd), 0)      as total_profit_usd,
                COALESCE(SUM(s.total_amount_from_usd), 0) as total_amount_from_usd,
                COALESCE(SUM(s.total_amount_to_usd), 0)   as total_amount_to_usd
            ');

        // Search filter
        if ($search !== null && $search !== '') {
            $searchTerm = mb_strtolower($search);

            $query->where(function ($q) use ($searchTerm) {
                // По tech_name (частичное совпадение, case-insensitive)
                $q->whereRaw('LOWER(d.tech_name) LIKE ?', ['%' . $searchTerm . '%']);

                // Если search похоже на число — ищем по ID направления
                if (ctype_digit($searchTerm)) {
                    $q->orWhere('d.id', (int) $searchTerm);
                }
            });
        }

        $query
            ->groupBy('d.id', 'd.tech_name', 'd.status', 'd.deleted_at')
            ->having('total_orders', '>', 0)
            ->orderByDesc('total_orders');

        $paginator = $query->paginate($perPage);

        // Преобразуем элементы пагинатора в тот же формат, что и в getDirectionsStats
        $items = collect($paginator->items())->map(function ($row) use ($totalOrdersAll) {
            $total     = (int) $row->total_orders;
            $completed = (int) $row->completed_orders;
            $rejected  = (int) $row->rejected_orders;
            $cancelled = (int) $row->cancelled_orders;

            $processed = $completed + $rejected + $cancelled;

            $sharePercent = $totalOrdersAll > 0
                ? round($total * 100 / $totalOrdersAll, 2)
                : 0.0;

            $successRate = $processed > 0
                ? round($completed * 100 / $processed, 2)
                : 0.0;

            $rejectRate = $processed > 0
                ? round($rejected * 100 / $processed, 2)
                : 0.0;

            $cancelRate = $processed > 0
                ? round($cancelled * 100 / $processed, 2)
                : 0.0;

            $category = $this->detectCategory(
                total: $total,
                sharePercent: $sharePercent,
                successRate: $successRate,
                rejectRate: $rejectRate
            );

            return [
                'id'                    => (int) $row->id,
                'tech_name'             => $row->tech_name,
                'name'                  => null,
                'status'                => (int) $row->status,
                'is_deleted'            => $row->deleted_at !== null,

                'total_orders'          => $total,
                'completed_orders'      => $completed,
                'rejected_orders'       => $rejected,
                'cancelled_orders'      => $cancelled,
                'processing_orders'     => (int) $row->processing_orders,

                'share_percent'         => $sharePercent,
                'success_rate'          => $successRate,
                'reject_rate'           => $rejectRate,
                'cancel_rate'           => $cancelRate,

                'total_profit_usd'      => (string) $row->total_profit_usd,
                'total_amount_from_usd' => (string) $row->total_amount_from_usd,
                'total_amount_to_usd'   => (string) $row->total_amount_to_usd,

                'category'              => $category,
            ];
        });

        return $paginator->setCollection($items);
    }

    /**
     * Пагинация по направлениям без спроса (нет заявок за период).
     */
    public function getNoDemandDirectionsPaginated(
        ?Carbon $from = null,
        ?Carbon $to = null,
        int $perPage = 30,
        ?string $search = null
    ): LengthAwarePaginator {
        $from = $from ?? now()->startOfMonth();
        $to   = $to   ?? now()->endOfMonth();

        $directionsTable = 'direction_exchange';
        $statsTable      = 'direction_exchange_stats_daily';

        $totalOrdersAll = (int) DB::table($statsTable)
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->sum('total_orders');

        $query = DB::table($directionsTable . ' as d')
            ->leftJoin($statsTable . ' as s', function ($join) use ($from, $to) {
                $join->on('s.direction_exchange_id', '=', 'd.id')
                    ->whereBetween('s.stat_date', [$from->toDateString(), $to->toDateString()]);
            })
            ->selectRaw('
                d.id,
                d.tech_name,
                d.status,
                d.deleted_at,
                COALESCE(SUM(s.total_orders), 0)          as total_orders,
                COALESCE(SUM(s.completed_orders), 0)      as completed_orders,
                COALESCE(SUM(s.rejected_orders), 0)       as rejected_orders,
                COALESCE(SUM(s.cancelled_orders), 0)      as cancelled_orders,
                COALESCE(SUM(s.processing_orders), 0)     as processing_orders,
                COALESCE(SUM(s.total_profit_usd), 0)      as total_profit_usd,
                COALESCE(SUM(s.total_amount_from_usd), 0) as total_amount_from_usd,
                COALESCE(SUM(s.total_amount_to_usd), 0)   as total_amount_to_usd
            ');

        // Search filter
        if ($search !== null && $search !== '') {
            $searchTerm = mb_strtolower($search);

            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(d.tech_name) LIKE ?', ['%' . $searchTerm . '%']);

                if (ctype_digit($searchTerm)) {
                    $q->orWhere('d.id', (int) $searchTerm);
                }
            });
        }

        $query
            ->groupBy('d.id', 'd.tech_name', 'd.status', 'd.deleted_at')
            ->having('total_orders', '=', 0)
            ->orderBy('d.id');

        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(function ($row) use ($totalOrdersAll) {
            $total     = (int) $row->total_orders;
            $completed = (int) $row->completed_orders;
            $rejected  = (int) $row->rejected_orders;
            $cancelled = (int) $row->cancelled_orders;

            $processed = $completed + $rejected + $cancelled;

            $sharePercent = $totalOrdersAll > 0
                ? round($total * 100 / $totalOrdersAll, 2)
                : 0.0;

            $successRate = $processed > 0
                ? round($completed * 100 / $processed, 2)
                : 0.0;

            $rejectRate = $processed > 0
                ? round($rejected * 100 / $processed, 2)
                : 0.0;

            $cancelRate = $processed > 0
                ? round($cancelled * 100 / $processed, 2)
                : 0.0;

            $category = $this->detectCategory(
                total: $total,
                sharePercent: $sharePercent,
                successRate: $successRate,
                rejectRate: $rejectRate
            );

            return [
                'id'                    => (int) $row->id,
                'tech_name'             => $row->tech_name,
                'name'                  => null,
                'status'                => (int) $row->status,
                'is_deleted'            => $row->deleted_at !== null,

                'total_orders'          => $total,
                'completed_orders'      => $completed,
                'rejected_orders'       => $rejected,
                'cancelled_orders'      => $cancelled,
                'processing_orders'     => (int) $row->processing_orders,

                'share_percent'         => $sharePercent,
                'success_rate'          => $successRate,
                'reject_rate'           => $rejectRate,
                'cancel_rate'           => $cancelRate,

                'total_profit_usd'      => (string) $row->total_profit_usd,
                'total_amount_from_usd' => (string) $row->total_amount_from_usd,
                'total_amount_to_usd'   => (string) $row->total_amount_to_usd,

                'category'              => $category,
            ];
        });

        return $paginator->setCollection($items);
    }

    /**
     * Классификация направления.
     */
    private function detectCategory(
        int $total,
        float $sharePercent,
        float $successRate,
        float $rejectRate
    ): string {
        if ($total === 0) {
            return 'no_demand';
        }

        // Популярное: большая доля и нормальная конверсия
        if ($sharePercent >= 10 && $successRate >= 60) {
            return 'popular';
        }

        // Проблемное: достаточно заявок и много отказов
        if ($total >= 10 && $rejectRate >= 30) {
            return 'problem';
        }

        return 'normal';
    }

    /**
     * Топ-10 популярных направлений за разные периоды
     * (в процентах и с изменением доли к предыдущему аналогичному периоду).
     */
    public function getTopDirectionsSummary(): array
    {
        $now = now();

        $statsTable = 'direction_exchange_stats_daily';

        // --- ALL TIME (за всё время) ---
        $minDate = DB::table($statsTable)->min('stat_date');

        $allTime = [];
        if ($minDate !== null) {
            $fromAll = Carbon::parse($minDate)->startOfDay();
            $toAll   = $now->copy()->endOfDay();

            $daysAll = $fromAll->diffInDays($toAll) + 1;

            $prevToAll   = $fromAll->copy()->subDay()->endOfDay();
            $prevFromAll = $prevToAll->copy()->subDays($daysAll - 1)->startOfDay();

            $allTime = $this->buildTopDirections(
                from: $fromAll,
                to: $toAll,
                prevFrom: $prevFromAll,
                prevTo: $prevToAll,
                limit: 10
            );
        }

        // --- MONTH (текущий месяц / предыдущий месяц) ---
        $fromMonth = $now->copy()->startOfMonth();
        $toMonth   = $now->copy()->endOfDay();

        $prevToMonth   = $fromMonth->copy()->subDay()->endOfDay();
        $prevFromMonth = $prevToMonth->copy()->startOfMonth();

        $monthly = $this->buildTopDirections(
            from: $fromMonth,
            to: $toMonth,
            prevFrom: $prevFromMonth,
            prevTo: $prevToMonth,
            limit: 10
        );

        // --- WEEK (последние 7 дней / предыдущие 7 дней) ---
        $toWeek   = $now->copy()->endOfDay();
        $fromWeek = $now->copy()->subDays(6)->startOfDay();

        $prevToWeek   = $fromWeek->copy()->subDay()->endOfDay();
        $prevFromWeek = $prevToWeek->copy()->subDays(6)->startOfDay();

        $weekly = $this->buildTopDirections(
            from: $fromWeek,
            to: $toWeek,
            prevFrom: $prevFromWeek,
            prevTo: $prevToWeek,
            limit: 10
        );

        return [
            'all_time' => [
                'period' => [
                    'from' => $minDate ? $fromAll->toDateString() : null,
                    'to'   => $minDate ? $toAll->toDateString() : null,
                ],
                'items' => $allTime,
            ],
            'monthly' => [
                'period' => [
                    'from' => $fromMonth->toDateString(),
                    'to'   => $toMonth->toDateString(),
                ],
                'items' => $monthly,
            ],
            'weekly' => [
                'period' => [
                    'from' => $fromWeek->toDateString(),
                    'to'   => $toWeek->toDateString(),
                ],
                'items' => $weekly,
            ],
        ];
    }

    /**
     * Внутренний помощник для построения топа направлений за период + изменение к предыдущему периоду.
     */
    private function buildTopDirections(
        Carbon $from,
        Carbon $to,
        Carbon $prevFrom,
        Carbon $prevTo,
        int $limit = 10
    ): array {
        $statsTable      = 'direction_exchange_stats_daily';
        $directionsTable = 'direction_exchange';

        // Текущий период: сумма заявок по направлениям
        $current = DB::table($statsTable)
            ->select('direction_exchange_id', DB::raw('SUM(total_orders) as total_orders'))
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('direction_exchange_id')
            ->get();

        $currentTotalOrders = (int) $current->sum('total_orders');

        if ($currentTotalOrders === 0) {
            // За период нет заявок вообще
            return [];
        }

        // Предыдущий период: сумма заявок по направлениям
        $previous = DB::table($statsTable)
            ->select('direction_exchange_id', DB::raw('SUM(total_orders) as total_orders'))
            ->whereBetween('stat_date', [$prevFrom->toDateString(), $prevTo->toDateString()])
            ->groupBy('direction_exchange_id')
            ->get();

        $previousTotalOrders = (int) $previous->sum('total_orders');

        // Индексы по direction_id
        $currentByDirection = $current
            ->keyBy('direction_exchange_id')
            ->map(fn ($row) => (int) $row->total_orders);

        $previousByDirection = $previous
            ->keyBy('direction_exchange_id')
            ->map(fn ($row) => (int) $row->total_orders);

        // Берём топ по текущему периоду (по количеству заявок)
        $topCurrent = $currentByDirection
            ->sortDesc()
            ->take($limit);

        if ($topCurrent->isEmpty()) {
            return [];
        }

        // ID направлений из топа
        $directionIds = $topCurrent->keys()->all();

        // Подтягиваем названия направлений
        $directions = DB::table($directionsTable . ' as d')
            ->whereIn('d.id', $directionIds)
            ->select('d.id', 'd.tech_name')
            ->get()
            ->keyBy('id');

        // Если надо, позже можно добавить human-readable имя (from→to), тут используем tech_name
        $result = [];

        foreach ($topCurrent as $directionId => $currentOrders) {
            $directionRow = $directions->get($directionId);

            $currentShare = $currentTotalOrders > 0
                ? round($currentOrders * 100 / $currentTotalOrders, 2)
                : 0.0;

            $previousOrders = (int) ($previousByDirection[$directionId] ?? 0);

            $previousShare = ($previousTotalOrders > 0 && $previousOrders > 0)
                ? round($previousOrders * 100 / $previousTotalOrders, 2)
                : 0.0;

            $change = $previousTotalOrders > 0
                ? round($currentShare - $previousShare, 2)
                : null; // если раньше не было данных — изменения считаем неизвестными

            $result[] = [
                'direction_id'    => (int) $directionId,
                'name'            => $directionRow?->tech_name ?? ('#' . $directionId),
                'total_orders'    => $currentOrders,
                'share_percent'   => $currentShare,
                'prev_share_percent' => $previousShare,
                'change_percent'  => $change,
            ];
        }

        return $result;
    }
}
