<?php

declare(strict_types=1);

namespace iEXPackages\Analytics\Http\Controllers\Reserves;

use App\Http\Controllers\Controller;
use iEXPackages\Analytics\Services\Reserves\ReserveLedgerAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReserveAnalyticsController extends Controller
{
    public function __construct(
        protected ReserveLedgerAnalyticsService $service
    ) {}

    /**
     * KPI по резервам за период + предыдущий период.
     * GET /admin/reports/reserves/kpis
     */
    public function kpis(Request $request)
    {
        $dateTo   = $request->input('date_to', now()->toDateString());
        $dateFrom = $request->input('date_from', Carbon::parse($dateTo)->subDays(6)->toDateString());

        $data = $this->service->kpis($request, $dateFrom, $dateTo);

        return response()->json([
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'reserve_id' => $request->input('reserve_id'),
                'currency_id' => $request->input('currency_id'),
                'direction_id' => $request->input('direction_id'),
                'task_id' => $request->input('task_id'),
                'task_public_id' => $request->input('task_public_id'),
                'action' => $request->input('action'),
                'source_type' => $request->input('source_type'),
            ],
            ...$data,
        ]);
    }

    /**
     * Агрегация по периодам day|week|month (month строится из days).
     * GET /admin/reports/reserves/periods?group_by=day
     */
    public function periods(Request $request)
    {
        $groupBy = $request->input('group_by', 'day');
        $data = $this->service->periods($request, $groupBy);

        return response()->json([
            'group_by' => $groupBy,
            'filters' => [
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'reserve_id' => $request->input('reserve_id'),
                'currency_id' => $request->input('currency_id'),
                'direction_id' => $request->input('direction_id'),
                'action' => $request->input('action'),
            ],
            'data' => $data,
        ]);
    }

    /**
     * Детальный список проводок (ledger) с пагинацией.
     * GET /admin/reports/reserves/ledger
     */
    public function ledger(Request $request)
    {
        $perPage = (int) $request->input('per_page', 30);
        $paginator = $this->service->listLedger($request, $perPage);

        return response()->json([
            'filters' => [
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'reserve_id' => $request->input('reserve_id'),
                'currency_id' => $request->input('currency_id'),
                'direction_id' => $request->input('direction_id'),
                'task_id' => $request->input('task_id'),
                'task_public_id' => $request->input('task_public_id'),
                'action' => $request->input('action'),
                'source_type' => $request->input('source_type'),
            ],
            'totals' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
            'data' => collect($paginator->items())->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'reserve_id' => (int) $row->reserve_id,
                    'task_id' => $row->task_id ? (int) $row->task_id : null,
                    'direction_exchange_id' => $row->direction_exchange_id ? (int) $row->direction_exchange_id : null,
                    'directionExchange' => $row->directionExchange ? [
                        'tech_name' => (string) ($row->directionExchange->tech_name ?? ''),
                    ] : null,
                    'action' => (string) $row->action,
                    'source_type' => (string) $row->source_type,
                    'source_id' => (int) ($row->source_id ?? 0),
                    'delta' => function_exists('iex_money_normalize') ? iex_money_normalize($row->delta, 18) : (string) $row->delta,
                    'balance_before' => function_exists('iex_money_normalize') ? iex_money_normalize($row->balance_before, 18) : (string) $row->balance_before,
                    'balance_after' => function_exists('iex_money_normalize') ? iex_money_normalize($row->balance_after, 18) : (string) $row->balance_after,
                    'occurred_at' => $row->occurred_at?->toISOString(),
                    'meta' => $row->meta ?? null,
                ];
            }),
        ]);
    }
}
