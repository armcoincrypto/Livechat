<?php

namespace iEXPackages\Analytics\Http\Controllers\Exchanges;

use App\Http\Controllers\Controller;
use App\Models\OrderProfitResult;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProfitAnalyticsController extends Controller
{
    /**
     * Универсальные фильтры, которые мы будем переиспользовать во всех методах.
     *
     * Поддерживаемые параметры:
     * - date_from, date_to (YYYY-MM-DD)
     * - direction_id (ID направления)
     * - task_public_id (поиск по публичному ID заявки)
     * - task_id (поиск по ID заявки)
     */
    protected function applyCommonFilters(
        Request $request,
        \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
    ) {
        $dateFrom     = $request->input('date_from');
        $dateTo       = $request->input('date_to');
        $directionId  = $request->input('direction_id');
        $taskPublicId = $request->input('task_public_id');
        $taskId       = $request->input('task_id');

        // режим работы по датам:
        // created (по умолчанию) или completed
        $dateMode = $request->input('date_mode', 'created');
        $dateColumn = $dateMode === 'completed'
            ? 'tasks.completed_at'
            : 'tasks.created_at';

        if ($dateFrom) {
            $dateFromParsed = Carbon::parse($dateFrom)->startOfDay();
            $query->where($dateColumn, '>=', $dateFromParsed);
        }

        if ($dateTo) {
            $dateToParsed = Carbon::parse($dateTo)->endOfDay();
            $query->where($dateColumn, '<=', $dateToParsed);
        }

        // Фильтры по заявке / направлению
        if ($directionId) {
            $query->where('tasks.id_direction_exchange', (int)$directionId);
        }

        if ($taskId) {
            $query->where('tasks.id', (int)$taskId);
        }

        if ($taskPublicId) {
            $query->where('tasks.public_id', 'LIKE', '%' . $taskPublicId . '%');
        }

        // Фильтр по статусу
        $status = $request->input('status');
        if ($status !== null && $status !== '') {
            $query->where('tasks.status', (int)$status);
        }
    }

    /**
     * 1.1) KPI-дашборд по прибыли за период и предыдущий период.
     *
     * GET /admin/reports/profit/kpis
     */
    public function profitKpis(Request $request)
    {
        // по умолчанию — последние 7 дней
        $dateTo   = $request->input('date_to', now()->toDateString());
        $dateFrom = $request->input('date_from', Carbon::parse($dateTo)->subDays(6)->toDateString());

        // длина периода в днях
        $periodDays = Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo)) + 1;

        // предыдущий период такой же длины
        $prevDateTo   = Carbon::parse($dateFrom)->subDay()->toDateString();
        $prevDateFrom = Carbon::parse($prevDateTo)->subDays($periodDays - 1)->toDateString();

        // статусы успешных заявок (реальная прибыль)
        $successStatuses = [4]; // Заявка исполнена; при необходимости дополни (7, 15 и т.п.)

        // Собираем агрегаты для периода
        $current = $this->aggregateProfitPeriod($request, $dateFrom, $dateTo, $successStatuses);
        $previous = $this->aggregateProfitPeriod($request, $prevDateFrom, $prevDateTo, $successStatuses);

        // считаем % изменения прибыли
        $changePercent = null;
        if ($previous['total_profit_usd'] !== 0.0) {
            $rawChange = (($current['total_profit_usd'] - $previous['total_profit_usd']) / $previous['total_profit_usd']) * 100;
            $changePercent = round($rawChange, 2);
        }

        return response()->json([
            'filters' => [
                'date_from'      => $dateFrom,
                'date_to'        => $dateTo,
                'direction_id'   => $request->input('direction_id'),
                'task_public_id' => $request->input('task_public_id'),
                'task_id'        => $request->input('task_id'),
            ],
            'current_period' => $current,
            'previous_period' => $previous,
            'change_percent' => $changePercent,
        ]);
    }

    /**
     * Вспомогательный метод для агрегатов прибыли по периоду.
     */
    protected function aggregateProfitPeriod(Request $request, string $dateFrom, string $dateTo, array $successStatuses): array
    {
        $query = OrderProfitResult::query()
            ->join('tasks', 'tasks.id', '=', 'order_profit_results.task_id');

        // принудительно задаём диапазон в реквесте, чтобы applyCommonFilters учитывал даты
        $snapshot = $request->all();
        $request->merge([
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'date_mode' => 'completed',
        ]);

        $this->applyCommonFilters($request, $query);

        // только успешные статусы
        if (!empty($successStatuses)) {
            $query->whereIn('tasks.status', $successStatuses);
        }

        $row = $query
            ->selectRaw('COUNT(order_profit_results.id) as total_orders, SUM(order_profit_results.profit_amount_usd) as total_profit_usd')
            ->first();

        // восстанавливаем реквест
        $request->replace($snapshot);

        $totalOrders = (int) ($row->total_orders ?? 0);
        $totalProfit = (float) ($row->total_profit_usd ?? 0.0);
        $avgProfit   = $totalOrders > 0 ? $totalProfit / $totalOrders : 0.0;

        return [
            'date_from'        => $dateFrom,
            'date_to'          => $dateTo,
            'total_orders'     => $totalOrders,
            'total_profit_usd' => $totalProfit,
            'avg_profit_usd'   => $avgProfit,
        ];
    }

    /**
     * 1) Список прибыли по заявкам (OrderProfitResult) с пагинацией и фильтрами.
     *
     * Это основной метод, который возвращает:
     * - список заявок с прибылью
     * - суммарную прибыль
     * - кол-во заявок
     *
     * GET /admin/reports/profit/orders
     */
    public function orders(Request $request)
    {
        $perPage = (int)$request->input('per_page', 50);

        $baseQuery = OrderProfitResult::query()
            ->join('tasks', 'tasks.id', '=', 'order_profit_results.task_id')
            ->leftJoin('direction_exchange as de', 'de.id', '=', 'tasks.id_direction_exchange')
            ->select([
                'order_profit_results.*',
                'tasks.created_at as task_created_at',
                'tasks.status as task_status',
                'tasks.public_id as task_public_id',
                'tasks.id_direction_exchange',
                'de.tech_name as direction_name',
            ])
            ->orderByDesc('order_profit_results.calculated_at');

        $this->applyCommonFilters($request, $baseQuery);

        // Пагинация
        $paginator = $baseQuery->paginate($perPage);

        // Клонируем запрос для подсчёта итогов (без limit/offset)
        $totalsQuery = clone $baseQuery;

        $totals = $totalsQuery
            ->selectRaw('COUNT(order_profit_results.id) as total_orders, SUM(order_profit_results.profit_amount_usd) as total_profit_usd')
            ->first();

        return response()->json([
            'filters' => [
                'date_from'      => $request->input('date_from'),
                'date_to'        => $request->input('date_to'),
                'direction_id'   => $request->input('direction_id'),
                'task_public_id' => $request->input('task_public_id'),
                'task_id'        => $request->input('task_id'),
            ],
            'totals' => [
                'total_orders'     => (int)($totals->total_orders ?? 0),
                'total_profit_usd' => (string)($totals->total_profit_usd ?? '0'),
            ],
            'data' => collect($paginator->items())->map(function ($row) {
                /** @var \App\Models\OrderProfitResult $row */
                return [
                    'id'                       => $row->id,
                    'task_id'                  => $row->task_id,
                    'task_public_id'           => $row->task_public_id,
                    'task_status'              => $row->task_status,
                    'task_created_at' => $row->task_created_at
                        ? Carbon::parse($row->task_created_at)->format('c')
                        : null,

                    'direction_id'             => $row->id_direction_exchange,
                    'direction_name'           => $row->direction_name,

                    'profit_amount'            => (string)$row->profit_amount,
                    'profit_currency_code'     => $row->profit_currency_code,
                    'profit_amount_usd'        => (string)$row->profit_amount_usd,
                    'base_currency_code'       => $row->base_currency_code,
                    'profit_percent_effective' => (string)$row->profit_percent_effective,

                    'calculated_at'            => $row->calculated_at ? $row->calculated_at->toDateTimeString() : null,
                    'created_at'               => $row->created_at ? $row->created_at->toDateTimeString() : null,
                    'updated_at'               => $row->updated_at ? $row->updated_at->toDateTimeString() : null,
                ];
            }),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * 2) Аггрегированная статистика по периодам (день / неделя / месяц).
     *
     * Возвращает массив периодов с суммой прибыли и количеством заявок.
     *
     * Параметры:
     * - group_by: day|week|month
     *
     * GET /admin/reports/profit/periods
     */
    public function periods(Request $request)
    {
        $groupBy = $request->input('group_by', 'day'); // day|week|month

        $query = OrderProfitResult::query()
            ->join('tasks', 'tasks.id', '=', 'order_profit_results.task_id');

        // для агрегации по прибыли работаем по completed_at
        $request->merge([
            'date_mode' => 'completed',
        ]);

        $this->applyCommonFilters($request, $query);

        // В зависимости от group_by строим группировку
        switch ($groupBy) {
            case 'week':
                // Группируем по году и номеру недели
                $query->selectRaw("
                    YEAR(tasks.completed_at) as year,
                    WEEK(tasks.completed_at, 1) as week,
                    COUNT(order_profit_results.id) as total_orders,
                    SUM(order_profit_results.profit_amount_usd) as total_profit_usd,
                    MIN(DATE(tasks.completed_at)) as period_start,
                    MAX(DATE(tasks.completed_at)) as period_end
                ");
                $query->groupBy('year', 'week');
                $query->orderBy('year')->orderBy('week');
                break;

            case 'month':
                // Группируем по году и месяцу
                $query->selectRaw("
                    YEAR(tasks.completed_at) as year,
                    MONTH(tasks.completed_at) as month,
                    COUNT(order_profit_results.id) as total_orders,
                    SUM(order_profit_results.profit_amount_usd) as total_profit_usd
                ");
                $query->groupBy('year', 'month');
                $query->orderBy('year')->orderBy('month');
                break;

            case 'day':
            default:
                // По умолчанию — по дням
                $query->selectRaw("
                    DATE(tasks.completed_at) as date,
                    COUNT(order_profit_results.id) as total_orders,
                    SUM(order_profit_results.profit_amount_usd) as total_profit_usd
                ");
                $query->groupBy(DB::raw('DATE(tasks.completed_at)'));
                $query->orderBy(DB::raw('DATE(tasks.completed_at)'));
                break;
        }

        $rows = $query->get();

        $data = [];

        foreach ($rows as $row) {
            if ($groupBy === 'day') {
                $label = Carbon::parse($row->date)->toDateString();
                $start = $label;
                $end   = $label;
            } elseif ($groupBy === 'week') {
                $start = Carbon::parse($row->period_start)->toDateString();
                $end   = Carbon::parse($row->period_end)->toDateString();
                $label = sprintf('%s (неделя %d)', $start . '–' . $end, $row->week);
            } else { // month
                $start = Carbon::create((int)$row->year, (int)$row->month, 1)->startOfMonth();
                $label = $start->translatedFormat('F Y'); // "Март 2025"
                $end   = $start->copy()->endOfMonth()->toDateString();
                $start = $start->toDateString();
            }

            $data[] = [
                'label'            => $label,
                'period_start'     => $start,
                'period_end'       => $end,
                'total_orders'     => (int)$row->total_orders,
                'total_profit_usd' => (string)$row->total_profit_usd,
            ];
        }

        return response()->json([
            'group_by' => $groupBy,
            'filters'  => [
                'date_from'      => $request->input('date_from'),
                'date_to'        => $request->input('date_to'),
                'direction_id'   => $request->input('direction_id'),
                'task_public_id' => $request->input('task_public_id'),
                'task_id'        => $request->input('task_id'),
            ],
            'data'     => $data,
        ]);
    }

    /**
     * 3) Статистика для построения дневного графика за месяц (или указанный период).
     *
     * Возвращает последовательность точек:
     * [
     *   { date: '2025-11-01', total_orders: 10, total_profit_usd: '12.34' },
     *   ...
     * ]
     *
     * GET /admin/reports/profit/daily-chart
     */
    public function dailyChart(Request $request)
    {
        // по умолчанию — последние 30 дней
        $dateTo   = $request->input('date_to', now()->toDateString());
        $dateFrom = $request->input('date_from', Carbon::parse($dateTo)->subDays(29)->toDateString());

        $request->merge([
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'date_mode' => 'completed',
        ]);

        $query = OrderProfitResult::query()
            ->join('tasks', 'tasks.id', '=', 'order_profit_results.task_id');

        $this->applyCommonFilters($request, $query);

        $query->selectRaw("
            DATE(tasks.completed_at) as date,
            COUNT(order_profit_results.id) as total_orders,
            SUM(order_profit_results.profit_amount_usd) as total_profit_usd
        ");
        $query->groupBy(DB::raw('DATE(tasks.completed_at)'));
        $query->orderBy(DB::raw('DATE(tasks.completed_at)'));

        $rows = $query->get();

        $data = $rows->map(function ($row) {
            return [
                'date'             => Carbon::parse($row->date)->toDateString(),
                'total_orders'     => (int)$row->total_orders,
                'total_profit_usd' => (string)$row->total_profit_usd,
            ];
        });

        return response()->json([
            'filters' => [
                'date_from'      => $dateFrom,
                'date_to'        => $dateTo,
                'direction_id'   => $request->input('direction_id'),
                'task_public_id' => $request->input('task_public_id'),
                'task_id'        => $request->input('task_id'),
            ],
            'data'    => $data,
        ]);
    }

    /**
     * 4) Детальная информация по одной заявке (можно использовать для модалки).
     *
     * GET /admin/reports/profit/order/{task}
     */
    public function orderDetails(Task $task)
    {
        $profit = $task->profitResult; // предполагается, что связь Task->profitResult есть

        if (!$profit) {
            return response()->json([
                'message' => 'По этой заявке прибыль ещё не рассчитана.',
            ], 404);
        }

        $direction = $task->direction_exchange;

        return response()->json([
            'task' => [
                'id'         => $task->id,
                'public_id'  => $task->public_id,
                'status'     => $task->status,
                'created_at' => $task->created_at?->toDateTimeString(),
            ],
            'direction' => [
                'id'            => $direction?->id,
                'name'          => $direction?->name ?? $direction?->tech_name,
                'currency_from' => $direction?->currency1?->code_currency?->name,
                'currency_to'   => $direction?->currency2?->code_currency?->name,
            ],
            'profit' => [
                'profit_amount'            => (string)$profit->profit_amount,
                'profit_currency_code'     => $profit->profit_currency_code,
                'profit_amount_usd'        => (string)$profit->profit_amount_usd,
                'base_currency_code'       => $profit->base_currency_code,
                'profit_percent_effective' => (string)$profit->profit_percent_effective,
                'rates_snapshot'           => $profit->rates_snapshot,
                'calculated_at'            => $profit->calculated_at?->toDateTimeString(),
            ],
            'breakdown' => $profit->breakdown_json ?? [],
        ]);
    }

    /**
     * Хелпер для агрегации прибыли по дням/неделям/месяцам
     * с расчётом % изменения и средней прибыли.
     *
     * @param Request $request
     * @param string $groupBy 'day' | 'week' | 'month'
     * @param string $dateFrom
     * @param string $dateTo
     * @return array<int, array<string,mixed>>
     */
    protected function buildPeriodStats(Request $request, string $groupBy, string $dateFrom, string $dateTo): array
    {
        $query = OrderProfitResult::query()
            ->join('tasks', 'tasks.id', '=', 'order_profit_results.task_id');

        // применяем общие фильтры: date_from/date_to/direction/task/...
        $this->applyCommonFilters($request, $query);

        switch ($groupBy) {
            case 'week':
                $query->selectRaw("
                YEAR(tasks.completed_at) as year,
                WEEK(tasks.completed_at, 1) as week,
                COUNT(order_profit_results.id) as total_orders,
                SUM(order_profit_results.profit_amount_usd) as total_profit_usd,
                MIN(DATE(tasks.completed_at)) as period_start,
                MAX(DATE(tasks.completed_at)) as period_end
            ");
                $query->groupBy('year', 'week');
                $query->orderBy('year')->orderBy('week');
                break;

            case 'month':
                $query->selectRaw("
                YEAR(tasks.completed_at) as year,
                MONTH(tasks.completed_at) as month,
                COUNT(order_profit_results.id) as total_orders,
                SUM(order_profit_results.profit_amount_usd) as total_profit_usd
            ");
                $query->groupBy('year', 'month');
                $query->orderBy('year')->orderBy('month');
                break;

            case 'day':
            default:
                $query->selectRaw("
                DATE(tasks.completed_at) as date,
                COUNT(order_profit_results.id) as total_orders,
                SUM(order_profit_results.profit_amount_usd) as total_profit_usd
            ");
                $query->groupBy(DB::raw('DATE(tasks.completed_at)'));
                $query->orderBy(DB::raw('DATE(tasks.completed_at)'));
                break;
        }

        $rows = $query->get();

        $result = [];
        $previousProfit = null;

        foreach ($rows as $row) {
            if ($groupBy === 'day') {
                $periodStart = Carbon::parse($row->date)->toDateString();
                $periodEnd   = $periodStart;
                $label       = $periodStart;
            } elseif ($groupBy === 'week') {
                $periodStart = Carbon::parse($row->period_start)->toDateString();
                $periodEnd   = Carbon::parse($row->period_end)->toDateString();
                $label       = sprintf('%s – %s (неделя %d)', $periodStart, $periodEnd, $row->week);
            } else { // month
                $start = Carbon::create((int)$row->year, (int)$row->month, 1)->startOfMonth();
                $label = $start->translatedFormat('F Y'); // "Март 2025"
                $periodStart = $start->toDateString();
                $periodEnd   = $start->copy()->endOfMonth()->toDateString();
            }

            $totalOrders = (int) $row->total_orders;
            $totalProfit = (float) $row->total_profit_usd;

            // средняя прибыль за период (на одну заявку)
            $avgProfit = $totalOrders > 0
                ? round($totalProfit / $totalOrders, 8)
                : 0.0;

            // % изменения и направление
            $changePercent   = null;
            $changeDirection = null;

            if ($previousProfit !== null) {
                if ($previousProfit != 0.0) {
                    $rawChange     = (($totalProfit - $previousProfit) / $previousProfit) * 100;
                    $changePercent = round($rawChange, 2);

                    if ($changePercent > 0) {
                        $changeDirection = 'up';
                    } elseif ($changePercent < 0) {
                        $changeDirection = 'down';
                    } else {
                        $changeDirection = 'flat';
                    }
                } else {
                    // предыдущая прибыль была 0 — процент не считаем, но направление можно
                    $changePercent = null;
                    if ($totalProfit > 0) {
                        $changeDirection = 'up';
                    } elseif ($totalProfit < 0) {
                        $changeDirection = 'down';
                    } else {
                        $changeDirection = 'flat';
                    }
                }
            }

            // определяем "аномальные" дни/периоды по порогу изменения прибыли
            $isAnomaly = false;
            $anomalyType = null;

            if ($changePercent !== null) {
                $threshold = 30.0; // порог в процентах, можно вынести в настройки
                if (abs($changePercent) >= $threshold) {
                    $isAnomaly = true;
                    $anomalyType = $changePercent > 0 ? 'positive' : 'negative';
                }
            }

            $result[] = [
                'label'             => $label,
                'period_start'      => $periodStart,
                'period_end'        => $periodEnd,
                'total_orders'      => $totalOrders,
                'total_profit_usd'  => (string) $row->total_profit_usd,
                'avg_profit_usd'    => (string) $avgProfit,
                'change_percent'    => $changePercent,
                'change_direction'  => $changeDirection, // up|down|flat|null
                'is_anomaly'        => $isAnomaly,
                'anomaly_type'      => $anomalyType,      // 'positive'|'negative'|null
            ];

            $previousProfit = $totalProfit;
        }

        return $result;
    }

    /**
     * 6) Общий обзор прибыли: дневная, недельная, месячная
     * с процентом изменений и средней прибылью.
     *
     * GET /admin/reports/profit/overview
     */
    public function profitOverview(Request $request)
    {
        // по умолчанию — последние 30 дней
        $dateTo   = $request->input('date_to', now()->toDateString());
        $dateFrom = $request->input('date_from', Carbon::parse($dateTo)->subDays(29)->toDateString());

        // фиксируем в реквесте, чтобы applyCommonFilters работал единообразно
        $request->merge([
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'date_mode' => 'completed',
        ]);

        // ежедневная статистика
        $daily = $this->buildPeriodStats($request, 'day', $dateFrom, $dateTo);

        // недельная статистика
        $weekly = $this->buildPeriodStats($request, 'week', $dateFrom, $dateTo);

        // месячная статистика
        $monthly = $this->buildPeriodStats($request, 'month', $dateFrom, $dateTo);

        return response()->json([
            'filters' => [
                'date_from'      => $dateFrom,
                'date_to'        => $dateTo,
                'direction_id'   => $request->input('direction_id'),
                'task_public_id' => $request->input('task_public_id'),
                'task_id'        => $request->input('task_id'),
            ],
            'daily'   => $daily,
            'weekly'  => $weekly,
            'monthly' => $monthly,
        ]);
    }

    /**
     * 7) Обзор по статусам заявок за период.
     *
     * Показывает, сколько заявок в каждом статусе и сколько прибыли приходится на успешные.
     *
     * GET /admin/reports/profit/status-overview
     */
    public function statusOverview(Request $request)
    {
        $dateTo   = $request->input('date_to', now()->toDateString());
        $dateFrom = $request->input('date_from', Carbon::parse($dateTo)->subDays(29)->toDateString());

        $request->merge([
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
        ]);

        // Берем заявки и статусы, плюс прибыль по успешным
        $query = DB::table('tasks')
            ->join('tasks_status as ts', 'ts.id', '=', 'tasks.status')
            ->leftJoin('order_profit_results as op', 'op.task_id', '=', 'tasks.id');

        // Поддержка общих фильтров напрямую на $query
        $this->applyCommonFilters($request, $query);

        $rows = $query
            ->selectRaw('
                ts.id as status_id,
                ts.name as status_name,
                ts.color as status_color,
                ts.class as status_class,
                COUNT(tasks.id) as total_tasks,
                SUM(op.profit_amount_usd) as total_profit_usd
            ')
            ->groupBy('ts.id', 'ts.name', 'ts.color', 'ts.class')
            ->orderBy('ts.id')
            ->get();

        $statuses = [];

        $groups = [
            'success' => [
                'label'            => 'Успешные',
                'status_ids'       => [4], // дополни если надо
                'total_tasks'      => 0,
                'total_profit_usd' => 0.0,
            ],
            'pending' => [
                'label'            => 'В работе',
                'status_ids'       => [2,3,7,8,9,12,13,15],
                'total_tasks'      => 0,
                'total_profit_usd' => 0.0,
            ],
            'failed' => [
                'label'            => 'Потерянные',
                'status_ids'       => [1,5,6,10,11,14],
                'total_tasks'      => 0,
                'total_profit_usd' => 0.0,
            ],
        ];

        foreach ($rows as $row) {
            $nameRu = $row->status_name;
            // name хранится как JSON { "ru": "...", ... } — пробуем декодировать
            $decoded = json_decode($row->status_name, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['ru'])) {
                $nameRu = $decoded['ru'];
            }

            $statuses[] = [
                'status_id'        => (int) $row->status_id,
                'name'             => $nameRu,
                'color'            => $row->status_color,
                'class'            => $row->status_class,
                'total_tasks'      => (int) $row->total_tasks,
                'total_profit_usd' => (float) ($row->total_profit_usd ?? 0.0),
            ];

            $statusId    = (int) $row->status_id;
            $tasksCount  = (int) $row->total_tasks;
            $profitValue = (float) ($row->total_profit_usd ?? 0.0);

            foreach ($groups as $key => &$group) {
                if (in_array($statusId, $group['status_ids'], true)) {
                    $group['total_tasks']      += $tasksCount;
                    $group['total_profit_usd'] += $profitValue;
                }
            }
            unset($group);
        }

        return response()->json([
            'filters' => [
                'date_from'      => $dateFrom,
                'date_to'        => $dateTo,
                'direction_id'   => $request->input('direction_id'),
                'task_public_id' => $request->input('task_public_id'),
                'task_id'        => $request->input('task_id'),
            ],
            'statuses' => $statuses,
            'groups'   => [
                'success' => [
                    'label'            => $groups['success']['label'],
                    'total_tasks'      => $groups['success']['total_tasks'],
                    'total_profit_usd' => $groups['success']['total_profit_usd'],
                ],
                'pending' => [
                    'label'            => $groups['pending']['label'],
                    'total_tasks'      => $groups['pending']['total_tasks'],
                    'total_profit_usd' => $groups['pending']['total_profit_usd'],
                ],
                'failed' => [
                    'label'            => $groups['failed']['label'],
                    'total_tasks'      => $groups['failed']['total_tasks'],
                    'total_profit_usd' => $groups['failed']['total_profit_usd'],
                ],
            ],
        ]);
    }

    /**
     * Прибыль по менеджерам / операторам.
     *
     * GET /admin/reports/profit/managers
     */
    public function managerProfit(Request $request)
    {
        // по умолчанию — последние 30 дней
        $dateTo   = $request->input('date_to', now()->toDateString());
        $dateFrom = $request->input('date_from', Carbon::parse($dateTo)->subDays(29)->toDateString());

        // для прибыли всегда работаем по completed_at
        $request->merge([
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'date_mode' => 'completed',
        ]);

        $query = OrderProfitResult::query()
            ->join('tasks', 'tasks.id', '=', 'order_profit_results.task_id')
            ->leftJoin('users as u', 'u.id', '=', 'tasks.id_who_completed');

        // применяем твои общие фильтры (даты, направление, task_id, status, и т.д.)
        $this->applyCommonFilters($request, $query);

        $rows = $query
            ->selectRaw('
                tasks.id_who_completed as manager_id,
                u.name as manager_name,
                COUNT(order_profit_results.id) as total_orders,
                SUM(order_profit_results.profit_amount_usd) as total_profit_usd,
                AVG(order_profit_results.profit_amount_usd) as avg_profit_usd,
                MIN(order_profit_results.profit_amount_usd) as min_profit_usd,
                MAX(order_profit_results.profit_amount_usd) as max_profit_usd
            ')
            ->groupBy('tasks.id_who_completed', 'u.name')
            ->orderByDesc('total_profit_usd')
            ->get();

        $managers = [];

        foreach ($rows as $row) {
            $managers[] = [
                'manager_id'        => (int) ($row->manager_id ?? 0),
                'manager_name'      => $row->manager_name ?? 'Не назначен',
                'total_orders'      => (int) $row->total_orders,
                'total_profit_usd'  => (float) $row->total_profit_usd,
                'avg_profit_usd'    => (float) ($row->avg_profit_usd ?? 0.0),
                'min_profit_usd'    => (float) ($row->min_profit_usd ?? 0.0),
                'max_profit_usd'    => (float) ($row->max_profit_usd ?? 0.0),
            ];
        }

        return response()->json([
            'filters' => [
                'date_from'      => $dateFrom,
                'date_to'        => $dateTo,
                'direction_id'   => $request->input('direction_id'),
                'task_public_id' => $request->input('task_public_id'),
                'task_id'        => $request->input('task_id'),
                'status'         => $request->input('status'),
            ],
            'managers' => $managers,
        ]);
    }

    /**
     * Heatmap прибыли по дням недели и часам.
     *
     * GET /admin/reports/profit/heatmap
     */
    public function profitHeatmap(Request $request)
    {
        // по умолчанию — последние 30 дней
        $dateTo   = $request->input('date_to', now()->toDateString());
        $dateFrom = $request->input('date_from', \Illuminate\Support\Carbon::parse($dateTo)->subDays(29)->toDateString());

        // для прибыли работаем по completed_at
        $request->merge([
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'date_mode' => 'completed',
        ]);

        $query = OrderProfitResult::query()
            ->join('tasks', 'tasks.id', '=', 'order_profit_results.task_id');

        $this->applyCommonFilters($request, $query);

        // DAYOFWEEK: 1=воскресенье, 2=понедельник..., 7=суббота
        $rows = $query
            ->selectRaw('
                DAYOFWEEK(tasks.completed_at) as weekday,
                HOUR(tasks.completed_at) as hour,
                COUNT(order_profit_results.id) as total_orders,
                SUM(order_profit_results.profit_amount_usd) as total_profit_usd
            ')
            ->whereNotNull('tasks.completed_at')
            ->groupBy('weekday', 'hour')
            ->orderBy('weekday')
            ->orderBy('hour')
            ->get();

        $heatmap = [];

        foreach ($rows as $row) {
            $weekday = (int) $row->weekday; // 1..7
            $hour    = (int) $row->hour;    // 0..23

            $heatmap[] = [
                'weekday'          => $weekday,
                'weekday_label'    => $this->mapWeekdayLabel($weekday),
                'hour'             => $hour,
                'total_orders'     => (int) $row->total_orders,
                'total_profit_usd' => (float) $row->total_profit_usd,
            ];
        }

        return response()->json([
            'filters' => [
                'date_from'      => $dateFrom,
                'date_to'        => $dateTo,
                'direction_id'   => $request->input('direction_id'),
                'task_public_id' => $request->input('task_public_id'),
                'task_id'        => $request->input('task_id'),
                'status'         => $request->input('status'),
            ],
            'data' => $heatmap,
        ]);
    }

    /**
     * Преобразует номер дня недели (1..7) в русскую метку.
     */
    protected function mapWeekdayLabel(int $weekday): string
    {
        // MySQL: 1 = воскресенье ... 7 = суббота
        return match ($weekday) {
            2 => 'Понедельник',
            3 => 'Вторник',
            4 => 'Среда',
            5 => 'Четверг',
            6 => 'Пятница',
            7 => 'Суббота',
            1 => 'Воскресенье',
            default => 'Неизвестно',
        };
    }
}
