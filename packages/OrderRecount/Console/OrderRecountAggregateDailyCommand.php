<?php

declare(strict_types=1);

namespace iEXPackages\OrderRecount\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use iEXPackages\OrderRecount\Models\OrderRecountAggregateState;

final class OrderRecountAggregateDailyCommand extends Command
{
    protected $signature = 'order-recount:aggregate-daily {--chunk=5000}';
    protected $description = 'OrderRecount: aggregate order_recount_audit into daily stats';

    public function handle(): int
    {
        $chunk = max(100, (int)$this->option('chunk'));

        $state = OrderRecountAggregateState::query()->find('daily');
        if (!$state) {
            $this->error('Aggregate state not found (daily)');
            return self::FAILURE;
        }

        $lastId = (int)$state->last_audit_id;
        $maxIdProcessed = $lastId;

        while (true) {
            $rows = DB::table('order_recount_audit')
                ->select(['id','policy_id','decision','reason_code','meta','created_at'])
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($chunk)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $agg = []; // key => aggregated row

            foreach ($rows as $r) {
                $maxIdProcessed = max($maxIdProcessed, (int)$r->id);

                $meta = is_array($r->meta) ? $r->meta : (json_decode((string)$r->meta, true) ?: []);

                $trigger = (string)($meta['trigger'] ?? 'unknown');

                // scope: если это policy — берём scope из meta, иначе floating
                $scopeType = (string)($meta['policy_scope_type'] ?? '');
                $scopeId   = isset($meta['policy_scope_id']) ? (int)$meta['policy_scope_id'] : null;

                if ($scopeType === '') {
                    // если это floating_executed — пометим как floating
                    $scopeType = (($r->reason_code ?? '') === 'floating_executed') ? 'floating' : 'global';
                    $scopeId = null;
                }

                $day = CarbonImmutable::parse($r->created_at)->toDateString();

                $decision = (string)$r->decision;
                $reason   = (string)($r->reason_code ?? 'ok');

                $perf = isset($meta['perf_ms']) ? (int)$meta['perf_ms'] : null;
                $calc = isset($meta['calc_ms']) ? (int)$meta['calc_ms'] : null;

                // recount: берём recount_total_ms если есть, иначе recount_ms
                $rec = null;
                if (isset($meta['recount_total_ms'])) $rec = (int)$meta['recount_total_ms'];
                elseif (isset($meta['recount_ms'])) $rec = (int)$meta['recount_ms'];

                $k = implode('|', [
                    $day,
                    $trigger,
                    $decision,
                    $reason,
                    $scopeType,
                    (string)($scopeId ?? 'null'),
                ]);

                if (!isset($agg[$k])) {
                    $agg[$k] = [
                        'day' => $day,
                        'trigger' => $trigger,
                        'decision' => $decision,
                        'reason_code' => $reason,
                        'scope_type' => $scopeType,
                        'scope_id' => $scopeId,

                        'events_count' => 0,

                        'perf_ms_sum' => 0,
                        'calc_ms_sum' => 0,
                        'recount_ms_sum' => 0,

                        'perf_ms_count' => 0,
                        'calc_ms_count' => 0,
                        'recount_ms_count' => 0,

                        'updated_at' => now(),
                    ];
                }

                $agg[$k]['events_count']++;

                if ($perf !== null && $perf >= 0) {
                    $agg[$k]['perf_ms_sum'] += $perf;
                    $agg[$k]['perf_ms_count']++;
                }
                if ($calc !== null && $calc >= 0) {
                    $agg[$k]['calc_ms_sum'] += $calc;
                    $agg[$k]['calc_ms_count']++;
                }
                if ($rec !== null && $rec >= 0) {
                    $agg[$k]['recount_ms_sum'] += $rec;
                    $agg[$k]['recount_ms_count']++;
                }
            }

            // upsert increments
            $rowsToUpsert = array_values($agg);

            // MySQL UPSERT with increments:
            // We'll do it via raw query per row for correctness, but still batched by chunk keys.
            // For simplicity and stability:
            foreach ($rowsToUpsert as $row) {
                DB::table('order_recount_daily_stats')->updateOrInsert(
                    [
                        'day' => $row['day'],
                        'trigger' => $row['trigger'],
                        'decision' => $row['decision'],
                        'reason_code' => $row['reason_code'],
                        'scope_type' => $row['scope_type'],
                        'scope_id' => $row['scope_id'],
                    ],
                    [
                        'events_count' => DB::raw('events_count + ' . (int)$row['events_count']),
                        'perf_ms_sum' => DB::raw('perf_ms_sum + ' . (int)$row['perf_ms_sum']),
                        'calc_ms_sum' => DB::raw('calc_ms_sum + ' . (int)$row['calc_ms_sum']),
                        'recount_ms_sum' => DB::raw('recount_ms_sum + ' . (int)$row['recount_ms_sum']),
                        'perf_ms_count' => DB::raw('perf_ms_count + ' . (int)$row['perf_ms_count']),
                        'calc_ms_count' => DB::raw('calc_ms_count + ' . (int)$row['calc_ms_count']),
                        'recount_ms_count' => DB::raw('recount_ms_count + ' . (int)$row['recount_ms_count']),
                        'updated_at' => now(),
                    ]
                );
            }

            $lastId = $maxIdProcessed;
        }

        if ($maxIdProcessed > (int)$state->last_audit_id) {
            $state->last_audit_id = $maxIdProcessed;
            $state->updated_at = now();
            $state->save();
        }

        $this->info("Aggregated up to audit_id={$maxIdProcessed}");
        return self::SUCCESS;
    }
}
