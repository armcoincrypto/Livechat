<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bounded 30-day retention prune for attribution analytics tables.
 *
 * Prunes:
 *  1) session_attribution_events by occurred_at (C.3E)
 *  2) session_attributions by last_seen_at / first_seen_at (C.3C)
 *
 * Never touches tasks, orders, payments, or other financial tables.
 * No hard FK: orphan task.session_attribution_id pointers are allowed.
 */
final class AnalyticsAttributionPruneCommand extends Command
{
    protected $signature = 'analytics:attribution-prune
        {--days=30 : Retain rows newer than N days (policy floor 30)}
        {--batch=500 : Rows deleted per batch}
        {--max-rows=5000 : Maximum rows deleted per table in this invocation}
        {--sleep-ms=50 : Sleep between batches in milliseconds}
        {--dry-run : Report candidates without deleting}';

    protected $description = 'Prune attribution sessions/events older than retention (default 30 days) in bounded batches';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $batch = (int) $this->option('batch');
        $maxRows = (int) $this->option('max-rows');
        $sleepMs = (int) $this->option('sleep-ms');
        $dryRun = (bool) $this->option('dry-run');

        if ($days < 30) {
            $this->error('Refuse days < 30 (attribution retention policy floor).');

            return self::FAILURE;
        }
        if ($days > 365) {
            $this->error('Refuse days > 365 (use a reasonable retention window).');

            return self::FAILURE;
        }
        if ($batch < 10 || $batch > 5000) {
            $this->error('batch must be between 10 and 5000.');

            return self::FAILURE;
        }
        if ($maxRows < 1 || $maxRows > 100000) {
            $this->error('max-rows must be between 1 and 100000 per invocation.');

            return self::FAILURE;
        }
        if ($sleepMs < 0 || $sleepMs > 5000) {
            $this->error('sleep-ms must be between 0 and 5000.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $this->line('cutoff='.$cutoff->toDateTimeString());
        $this->line('batch_size='.$batch);
        $this->line('max_rows='.$maxRows);
        $this->line('dry_run='.($dryRun ? '1' : '0'));

        $eventsDeleted = 0;
        $sessionsDeleted = 0;

        try {
            if (DB::getSchemaBuilder()->hasTable('session_attribution_events')) {
                $eventsCandidates = (int) DB::table('session_attribution_events')
                    ->where('occurred_at', '<', $cutoff)
                    ->count();
                $this->line('event_candidate_rows='.$eventsCandidates);
                if (! $dryRun && $eventsCandidates > 0) {
                    $eventsDeleted = $this->deleteBatched(
                        'session_attribution_events',
                        fn ($q) => $q->where('occurred_at', '<', $cutoff),
                        $batch,
                        $maxRows,
                        $sleepMs,
                    );
                }
                $this->line('events_deleted='.$eventsDeleted);
            } else {
                $this->line('event_candidate_rows=0');
                $this->line('events_deleted=0');
                $this->line('session_attribution_events table absent — skip event prune');
            }

            if (! DB::getSchemaBuilder()->hasTable('session_attributions')) {
                $this->info('session_attributions table absent — nothing more to prune.');

                return self::SUCCESS;
            }

            $sessionCandidates = (int) DB::table('session_attributions')
                ->where(function ($q) use ($cutoff) {
                    $q->where('last_seen_at', '<', $cutoff)
                        ->orWhere(function ($q2) use ($cutoff) {
                            $q2->whereNull('last_seen_at')
                                ->where('first_seen_at', '<', $cutoff);
                        });
                })
                ->count();
            $this->line('session_candidate_rows='.$sessionCandidates);
            $this->line('tables_touched=session_attribution_events,session_attributions');

            if ($dryRun) {
                $this->info('DRY_RUN complete — no rows deleted.');

                return self::SUCCESS;
            }

            if ($sessionCandidates > 0) {
                $sessionsDeleted = $this->deleteBatched(
                    'session_attributions',
                    function ($q) use ($cutoff) {
                        $q->where(function ($inner) use ($cutoff) {
                            $inner->where('last_seen_at', '<', $cutoff)
                                ->orWhere(function ($q2) use ($cutoff) {
                                    $q2->whereNull('last_seen_at')
                                        ->where('first_seen_at', '<', $cutoff);
                                });
                        });
                    },
                    $batch,
                    $maxRows,
                    $sleepMs,
                );
            }
            $this->info("OK events_deleted={$eventsDeleted} sessions_deleted={$sessionsDeleted}");
        } catch (Throwable $e) {
            Log::warning('attribution_prune_failed', ['class' => $e::class]);
            $this->error('prune failed: '.$e::class);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  callable(\Illuminate\Database\Query\Builder):void  $applyWhere
     */
    private function deleteBatched(
        string $table,
        callable $applyWhere,
        int $batch,
        int $maxRows,
        int $sleepMs,
    ): int {
        $deletedTotal = 0;

        while ($deletedTotal < $maxRows) {
            $limit = min($batch, $maxRows - $deletedTotal);
            $query = DB::table($table);
            $applyWhere($query);
            $ids = $query->orderBy('id')->limit($limit)->pluck('id')->all();
            if ($ids === []) {
                break;
            }

            $deleted = (int) DB::table($table)->whereIn('id', $ids)->delete();
            $deletedTotal += $deleted;

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
            if ($deleted === 0) {
                break;
            }
        }

        return $deletedTotal;
    }
}
