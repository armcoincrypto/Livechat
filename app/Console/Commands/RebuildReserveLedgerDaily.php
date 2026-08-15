<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class RebuildReserveLedgerDaily extends Command
{
    protected $signature = 'reserve-ledger:daily
        {--from= : YYYY-MM-DD}
        {--to= : YYYY-MM-DD}
        {--days=60 : Если from/to не переданы — пересчитать последние N дней}';

    protected $description = 'Rebuild reserve_ledger_daily from reserve_ledgers';

    public function handle(): int
    {
        // -------------------------------
        // 1) Определяем период
        // -------------------------------
        $to = $this->option('to');
        $from = $this->option('from');

        if (!$to || !$from) {
            $days = max(1, (int) $this->option('days'));
            $to = now()->toDateString();
            $from = Carbon::parse($to)->subDays($days - 1)->toDateString();
        }

        $fromDt = Carbon::parse($from)->startOfDay();
        $toDt   = Carbon::parse($to)->endOfDay();

        $this->info("Rebuilding reserve_ledger_daily for {$from} .. {$to}");

        DB::transaction(function () use ($fromDt, $toDt) {

            // -------------------------------
            // 2) Агрегация по action
            // -------------------------------
            $rows = DB::table('reserve_ledgers')
                ->whereBetween('occurred_at', [$fromDt, $toDt])
                ->selectRaw('
                    DATE(occurred_at) as day,
                    reserve_id,
                    direction_exchange_id,
                    currency_id,
                    action,

                    COUNT(*) as count_total,

                    SUM(CASE WHEN delta > 0 THEN 1 ELSE 0 END) as count_in,
                    SUM(CASE WHEN delta < 0 THEN 1 ELSE 0 END) as count_out,

                    COALESCE(SUM(delta), 0) as sum_delta,
                    COALESCE(SUM(CASE WHEN delta > 0 THEN delta ELSE 0 END), 0) as sum_in,
                    COALESCE(SUM(CASE WHEN delta < 0 THEN ABS(delta) ELSE 0 END), 0) as sum_out
                ')
                ->groupByRaw('DATE(occurred_at), reserve_id, direction_exchange_id, currency_id, action')
                ->get();

            $payload = [];
            foreach ($rows as $r) {
                $payload[] = [
                    'day' => (string) $r->day,
                    'reserve_id' => (int) $r->reserve_id,
                    'direction_exchange_id' => $r->direction_exchange_id !== null ? (int) $r->direction_exchange_id : null,
                    'currency_id' => $r->currency_id !== null ? (int) $r->currency_id : null,
                    'action' => (string) $r->action,

                    'count_total' => (int) $r->count_total,
                    'count_in' => (int) $r->count_in,
                    'count_out' => (int) $r->count_out,

                    'sum_delta' => (string) $r->sum_delta,
                    'sum_in' => (string) $r->sum_in,
                    'sum_out' => (string) $r->sum_out,

                    'closing_balance' => null,

                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($payload) {
                DB::table('reserve_ledger_daily')->upsert(
                    $payload,
                    ['day', 'reserve_id', 'action', 'direction_exchange_id'],
                    [
                        'currency_id',
                        'count_total', 'count_in', 'count_out',
                        'sum_delta', 'sum_in', 'sum_out',
                        'closing_balance',
                        'updated_at',
                    ]
                );
            }

            // -------------------------------
            // 3) Итог дня (day_total)
            // -------------------------------
            $dayTotals = DB::table('reserve_ledgers')
                ->whereBetween('occurred_at', [$fromDt, $toDt])
                ->selectRaw('
                    DATE(occurred_at) as day,
                    reserve_id,
                    MAX(currency_id) as currency_id,

                    COUNT(*) as count_total,
                    SUM(CASE WHEN delta > 0 THEN 1 ELSE 0 END) as count_in,
                    SUM(CASE WHEN delta < 0 THEN 1 ELSE 0 END) as count_out,

                    COALESCE(SUM(delta), 0) as sum_delta,
                    COALESCE(SUM(CASE WHEN delta > 0 THEN delta ELSE 0 END), 0) as sum_in,
                    COALESCE(SUM(CASE WHEN delta < 0 THEN ABS(delta) ELSE 0 END), 0) as sum_out
                ')
                ->groupByRaw('DATE(occurred_at), reserve_id')
                ->get();

            $totalsPayload = [];
            foreach ($dayTotals as $r) {
                $totalsPayload[] = [
                    'day' => (string) $r->day,
                    'reserve_id' => (int) $r->reserve_id,
                    'direction_exchange_id' => null,
                    'currency_id' => $r->currency_id !== null ? (int) $r->currency_id : null,
                    'action' => 'day_total',

                    'count_total' => (int) $r->count_total,
                    'count_in' => (int) $r->count_in,
                    'count_out' => (int) $r->count_out,

                    'sum_delta' => (string) $r->sum_delta,
                    'sum_in' => (string) $r->sum_in,
                    'sum_out' => (string) $r->sum_out,

                    'closing_balance' => null,

                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($totalsPayload) {
                DB::table('reserve_ledger_daily')->upsert(
                    $totalsPayload,
                    ['day', 'reserve_id', 'action', 'direction_exchange_id'],
                    [
                        'currency_id',
                        'count_total', 'count_in', 'count_out',
                        'sum_delta', 'sum_in', 'sum_out',
                        'closing_balance',
                        'updated_at',
                    ]
                );
            }

            // -------------------------------
            // 4) closing_balance (последняя запись дня)
            // -------------------------------
            $lastBalances = DB::table('reserve_ledgers')
                ->whereBetween('occurred_at', [$fromDt, $toDt])
                ->selectRaw('
                    DATE(occurred_at) as day,
                    reserve_id,
                    balance_after,
                    occurred_at,
                    id
                ')
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->get()
                ->groupBy(fn ($x) => $x->day . ':' . $x->reserve_id)
                ->map(fn ($group) => $group->last());

            foreach ($lastBalances as $row) {
                DB::table('reserve_ledger_daily')
                    ->where('day', (string) $row->day)
                    ->where('reserve_id', (int) $row->reserve_id)
                    ->whereNull('direction_exchange_id')
                    ->where('action', 'day_total')
                    ->update([
                        'closing_balance' => (string) $row->balance_after,
                        'updated_at' => now(),
                    ]);
            }
        });

        $this->info('OK: reserve_ledger_daily rebuilt successfully');

        return self::SUCCESS;
    }
}
