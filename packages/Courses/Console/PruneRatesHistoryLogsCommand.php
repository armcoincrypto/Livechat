<?php

declare(strict_types=1);

namespace iEXPackages\Courses\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Batched, bounded prune for rates_history_logs.
 *
 * Retention default is 90 days: analytics consumers only need ~24h, admin charts
 * default to 1 day, and indexed created_at makes time-based deletion safe.
 *
 * Never issues one giant DELETE. Deletes by primary-key batches with optional
 * sleep, max-rows ceiling, and dry-run mode. Scheduler-safe via withoutOverlapping.
 */
final class PruneRatesHistoryLogsCommand extends Command
{
    protected $signature = 'rates-history:prune
        {--days=90 : Retain rows newer than N days}
        {--batch=5000 : Rows deleted per batch}
        {--max-rows=50000 : Maximum rows deleted in this invocation}
        {--sleep-ms=50 : Sleep between batches in milliseconds}
        {--skip-count : Skip expensive candidate COUNT (use during drain loops)}
        {--dry-run : Report candidates without deleting}';

    protected $description = 'Prune rates_history_logs older than N days in bounded primary-key batches';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $batch = (int) $this->option('batch');
        $maxRows = (int) $this->option('max-rows');
        $sleepMs = (int) $this->option('sleep-ms');
        $skipCount = (bool) $this->option('skip-count');
        $dryRun = (bool) $this->option('dry-run');

        if ($days < 30) {
            $this->error('Refuse days < 30 (safety floor for rate-history retention).');
            return self::FAILURE;
        }
        if ($batch < 100 || $batch > 20000) {
            $this->error('batch must be between 100 and 20000.');
            return self::FAILURE;
        }
        if ($maxRows < 1 || $maxRows > 2000000) {
            $this->error('max-rows must be between 1 and 2000000 per invocation.');
            return self::FAILURE;
        }
        if ($sleepMs < 0 || $sleepMs > 5000) {
            $this->error('sleep-ms must be between 0 and 5000.');
            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $candidateRows = null;
        if (! $skipCount || $dryRun) {
            $candidateRows = (int) DB::table('rates_history_logs')
                ->where('created_at', '<', $cutoff)
                ->count();
        }

        $oldestRetained = null;
        if (! $skipCount || $dryRun) {
            $oldestRetained = DB::table('rates_history_logs')
                ->where('created_at', '>=', $cutoff)
                ->orderBy('created_at')
                ->value('created_at');
        }

        $estimatedBatches = $candidateRows !== null && $candidateRows > 0
            ? (int) ceil(min($candidateRows, $maxRows) / $batch)
            : (int) ceil($maxRows / $batch);

        $this->line('cutoff=' . $cutoff->toDateTimeString());
        $this->line('candidate_rows=' . ($candidateRows === null ? 'skipped' : (string) $candidateRows));
        $this->line('oldest_retained=' . ($oldestRetained ?? ($skipCount ? 'skipped' : 'none')));
        $this->line('batch_size=' . $batch);
        $this->line('max_rows=' . $maxRows);
        $this->line('estimated_batches=' . $estimatedBatches);
        $this->line('dry_run=' . ($dryRun ? '1' : '0'));

        if ($dryRun) {
            $this->info('DRY_RUN complete — no rows deleted.');
            return self::SUCCESS;
        }

        if ($candidateRows === 0) {
            $this->info('No rates_history_logs rows older than retention window.');
            return self::SUCCESS;
        }

        $deletedTotal = 0;
        $batches = 0;

        try {
            while ($deletedTotal < $maxRows) {
                $limit = min($batch, $maxRows - $deletedTotal);
                $ids = DB::table('rates_history_logs')
                    ->where('created_at', '<', $cutoff)
                    ->orderBy('id')
                    ->limit($limit)
                    ->pluck('id')
                    ->all();

                if ($ids === []) {
                    break;
                }

                $deleted = (int) DB::table('rates_history_logs')
                    ->whereIn('id', $ids)
                    ->delete();

                $deletedTotal += $deleted;
                $batches++;

                $this->line("batch={$batches} deleted={$deleted} cumulative={$deletedTotal}");

                if ($deleted === 0) {
                    break;
                }

                if ($sleepMs > 0 && $deletedTotal < $maxRows) {
                    usleep($sleepMs * 1000);
                }
            }
        } catch (Throwable $e) {
            Log::error('rates-history:prune failed', [
                'message' => $e->getMessage(),
                'deleted_before_failure' => $deletedTotal,
            ]);
            $this->error('Prune failed after deleting ' . $deletedTotal . ' rows: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("OK deleted={$deletedTotal} batches={$batches} days={$days}");
        Log::info('rates-history:prune completed', [
            'deleted' => $deletedTotal,
            'batches' => $batches,
            'days' => $days,
            'cutoff' => $cutoff->toDateTimeString(),
        ]);

        return self::SUCCESS;
    }
}
