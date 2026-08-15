<?php

namespace iEXPackages\Analytics\Http\Controllers\Exchanges;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

final class OrderRecountStatsController extends Controller
{
    public function daily(Request $request)
    {
        $from = (string)$request->query('from', now()->subDays(7)->toDateString());
        $to   = (string)$request->query('to', now()->toDateString());

        $scopeType = (string)$request->query('scope_type', '');
        $trigger   = (string)$request->query('trigger', '');
        $decision  = (string)$request->query('decision', '');

        $q = DB::table('order_recount_daily_stats')
            ->whereBetween('day', [$from, $to])
            ->orderBy('day');

        if ($scopeType !== '') $q->where('scope_type', $scopeType);
        if ($trigger !== '') $q->where('trigger', $trigger);
        if ($decision !== '') $q->where('decision', $decision);

        $rows = $q->get();

        // добавим вычисленные avg
        $data = $rows->map(function ($r) {
            $avgPerf = $r->perf_ms_count > 0 ? round($r->perf_ms_sum / $r->perf_ms_count, 2) : null;
            $avgCalc = $r->calc_ms_count > 0 ? round($r->calc_ms_sum / $r->calc_ms_count, 2) : null;
            $avgRec  = $r->recount_ms_count > 0 ? round($r->recount_ms_sum / $r->recount_ms_count, 2) : null;

            return [
                'day' => $r->day,
                'trigger' => $r->trigger,
                'decision' => $r->decision,
                'reason_code' => $r->reason_code,
                'scope_type' => $r->scope_type,
                'scope_id' => $r->scope_id,
                'events_count' => (int)$r->events_count,
                'avg_perf_ms' => $avgPerf,
                'avg_calc_ms' => $avgCalc,
                'avg_recount_ms' => $avgRec,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
