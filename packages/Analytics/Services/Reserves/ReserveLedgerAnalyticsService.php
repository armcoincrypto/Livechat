<?php

declare(strict_types=1);

namespace iEXPackages\Analytics\Services\Reserves;

use App\Models\ReserveLedger;
use App\Models\ReserveLedgerDaily;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ReserveLedgerAnalyticsService
{
    /**
     * Общие фильтры для ledger / daily.
     *
     * Поддерживаем:
     * - date_from, date_to (YYYY-MM-DD)
     * - reserve_id
     * - currency_id
     * - direction_id (direction_exchange_id)
     * - task_id
     * - task_public_id (требует join tasks)
     * - action
     * - source_type
     */
    public function applyCommonFilters(Request $request, QueryBuilder|EloquentBuilder $query, string $dateColumn): void
    {
        $dateFrom     = $request->input('date_from');
        $dateTo       = $request->input('date_to');
        $reserveId    = $request->input('reserve_id');
        $currencyId   = $request->input('currency_id');
        $directionId  = $request->input('direction_id');
        $taskId       = $request->input('task_id');
        $taskPublicId = $request->input('task_public_id');
        $action       = $request->input('action');
        $sourceType   = $request->input('source_type');

        if ($dateFrom) {
            $query->where($dateColumn, '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        if ($dateTo) {
            $query->where($dateColumn, '<=', Carbon::parse($dateTo)->endOfDay());
        }

        if ($reserveId) {
            $query->where('reserve_ledgers.reserve_id', (int) $reserveId);
        }

        if ($currencyId) {
            $query->where('reserve_ledgers.currency_id', (int) $currencyId);
        }

        if ($directionId) {
            $query->where('reserve_ledgers.direction_exchange_id', (int) $directionId);
        }

        if ($taskId) {
            $query->where('reserve_ledgers.task_id', (int) $taskId);
        }

        if ($taskPublicId) {
            // Требует join tasks
            $query->where('tasks.public_id', 'LIKE', '%' . $taskPublicId . '%');
        }

        if ($action !== null && $action !== '') {
            $query->where('reserve_ledgers.action', (string) $action);
        }

        if ($sourceType !== null && $sourceType !== '') {
            $query->where('reserve_ledgers.source_type', (string) $sourceType);
        }
    }

    /**
     * KPI по резервам за период и предыдущий период.
     */
    public function kpis(Request $request, string $dateFrom, string $dateTo): array
    {
        $periodDays = Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo)) + 1;
        $prevDateTo = Carbon::parse($dateFrom)->subDay()->toDateString();
        $prevDateFrom = Carbon::parse($prevDateTo)->subDays($periodDays - 1)->toDateString();

        $current = $this->aggregatePeriod($request, $dateFrom, $dateTo);
        $previous = $this->aggregatePeriod($request, $prevDateFrom, $prevDateTo);

        $changePercent = null;
        $prevNet = (float) ($previous['net_delta'] ?? 0);
        $curNet  = (float) ($current['net_delta'] ?? 0);

        if ($prevNet != 0.0) {
            $changePercent = round((($curNet - $prevNet) / $prevNet) * 100, 2);
        }

        return [
            'current_period' => $current,
            'previous_period' => $previous,
            'change_percent' => $changePercent,
        ];
    }

    /**
     * Агрегаты за период (быстро и понятно):
     * - total_rows
     * - total_delta (SUM(delta))
     * - out_total (сумма отрицательных delta по hold_out / manual_adjust и т.п.)
     * - in_total  (сумма положительных delta)
     * - actions breakdown
     */
    public function aggregatePeriod(Request $request, string $dateFrom, string $dateTo): array
    {
        $q = ReserveLedger::query()
            ->from('reserve_ledgers')
            ->selectRaw('COUNT(reserve_ledgers.id) as total_rows')
            ->selectRaw('COALESCE(SUM(reserve_ledgers.delta), 0) as net_delta')
            ->selectRaw('COALESCE(SUM(CASE WHEN reserve_ledgers.delta > 0 THEN reserve_ledgers.delta ELSE 0 END), 0) as in_total')
            ->selectRaw('COALESCE(SUM(CASE WHEN reserve_ledgers.delta < 0 THEN reserve_ledgers.delta ELSE 0 END), 0) as out_total');

        // под task_public_id нужен join
        $snapshot = $request->all();
        $request->merge(['date_from' => $dateFrom, 'date_to' => $dateTo]);

        if ($request->input('task_public_id')) {
            $q->join('tasks', 'tasks.id', '=', 'reserve_ledgers.task_id');
        }

        $this->applyCommonFilters($request, $q, 'reserve_ledgers.occurred_at');

        $row = $q->first();

        // breakdown по action
        $q2 = ReserveLedger::query()->from('reserve_ledgers');

        if ($request->input('task_public_id')) {
            $q2->join('tasks', 'tasks.id', '=', 'reserve_ledgers.task_id');
        }

        $this->applyCommonFilters($request, $q2, 'reserve_ledgers.occurred_at');

        $actions = $q2
            ->selectRaw('reserve_ledgers.action as action')
            ->selectRaw('COUNT(reserve_ledgers.id) as cnt')
            ->selectRaw('COALESCE(SUM(reserve_ledgers.delta), 0) as sum_delta')
            ->groupBy('reserve_ledgers.action')
            ->orderByDesc(DB::raw('ABS(SUM(reserve_ledgers.delta))'))
            ->get()
            ->map(fn ($r) => [
                'action' => (string) $r->action,
                'count' => (int) $r->cnt,
                'sum_delta' => $this->normMoney((string) $r->sum_delta),
            ])
            ->values()
            ->all();

        $request->replace($snapshot);

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'total_rows' => (int) ($row->total_rows ?? 0),
            'net_delta' => $this->normMoney((string) ($row->net_delta ?? '0')),
            'in_total' => $this->normMoney((string) ($row->in_total ?? '0')),
            'out_total' => $this->normMoney((string) ($row->out_total ?? '0')), // будет отрицательная строка
            'actions' => $actions,
        ];
    }

    /**
     * График/статистика по периодам: day|week|month
     * Для скорости используем reserve_ledger_daily (day).
     * week/month считаем агрегацией поверх daily (не нужен monthly table).
     */
    public function periods(Request $request, string $groupBy = 'day'): array
    {
        $groupBy = in_array($groupBy, ['day', 'week', 'month'], true) ? $groupBy : 'day';

        // Для daily берём витрину
        if ($groupBy === 'day') {
            $q = ReserveLedgerDaily::query()->from('reserve_ledger_daily');

            // date_from/date_to применяем к day
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            if ($dateFrom) $q->where('day', '>=', $dateFrom);
            if ($dateTo) $q->where('day', '<=', $dateTo);

            if ($request->input('reserve_id')) {
                $q->where('reserve_id', (int) $request->input('reserve_id'));
            }
            if ($request->input('currency_id')) {
                $q->where('currency_id', (int) $request->input('currency_id'));
            }
            if ($request->input('direction_id')) {
                $q->where('direction_exchange_id', (int) $request->input('direction_id'));
            }
            if ($request->input('action')) {
                $q->where('action', (string) $request->input('action'));
            }

            $rows = $q
                ->select([
                    'day',
                    DB::raw('SUM(count_total) as total_rows'),
                    DB::raw('SUM(sum_delta) as net_delta'),
                    DB::raw('SUM(sum_in) as in_total'),
                    DB::raw('SUM(sum_out) as out_total'),
                ])
                ->groupBy('day')
                ->orderBy('day')
                ->get();

            return $rows->map(fn ($r) => [
                'label' => (string) $r->day,
                'period_start' => (string) $r->day,
                'period_end' => (string) $r->day,
                'total_rows' => (int) $r->total_rows,
                'net_delta' => $this->normMoney((string) $r->net_delta),
                'in_total' => $this->normMoney((string) $r->in_total),
                'out_total' => $this->normMoney((string) $r->out_total),
            ])->values()->all();
        }

        // week/month считаем по daily витрине
        $daily = $this->periods($request, 'day');

        // группируем в PHP (быстро, т.к. daily не огромный)
        $bucket = [];
        foreach ($daily as $item) {
            $d = Carbon::parse($item['label']);

            if ($groupBy === 'week') {
                $key = $d->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
                $start = $d->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
                $end   = $d->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();
                $label = $start . '–' . $end;
            } else {
                $key = $d->copy()->startOfMonth()->toDateString();
                $start = $d->copy()->startOfMonth()->toDateString();
                $end   = $d->copy()->endOfMonth()->toDateString();
                $label = $d->translatedFormat('F Y');
            }

            $bucket[$key] ??= [
                'label' => $label,
                'period_start' => $start,
                'period_end' => $end,
                'total_rows' => 0,
                'net_delta' => '0',
                'in_total' => '0',
                'out_total' => '0',
            ];

            $bucket[$key]['total_rows'] += (int) $item['total_rows'];
            $bucket[$key]['net_delta'] = $this->normMoney((string) ((float)$bucket[$key]['net_delta'] + (float)$item['net_delta']));
            $bucket[$key]['in_total']  = $this->normMoney((string) ((float)$bucket[$key]['in_total'] + (float)$item['in_total']));
            $bucket[$key]['out_total'] = $this->normMoney((string) ((float)$bucket[$key]['out_total'] + (float)$item['out_total']));
        }

        // сортировка по ключу (date)
        ksort($bucket);

        return array_values($bucket);
    }

    /**
     * Список проводок (детально) с пагинацией — для UI.
     */
    public function listLedger(Request $request, int $perPage = 50)
    {
        $q = ReserveLedger::query()
            ->from('reserve_ledgers')
            ->with('directionExchange:id,tech_name')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if ($request->input('task_public_id')) {
            $q->join('tasks', 'tasks.id', '=', 'reserve_ledgers.task_id');
        }

        $this->applyCommonFilters($request, $q, 'reserve_ledgers.occurred_at');

        return $q->paginate($perPage);
    }

    private function normMoney(string|int|float|null $value): string
    {
        if (function_exists('iex_money_normalize')) {
            return iex_money_normalize($value, 18);
        }

        return (string) ($value ?? '0');
    }
}
