<?php

declare(strict_types=1);

namespace iEXPackages\OrderRecount\Jobs;

use App\Models\DirectionExchange;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use iEXPackages\OrderRecount\Support\PolicyRepository;

final class ScanCronCandidatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $chunk = 500,
        public readonly int $maxTasks = 20000,
        public readonly int $batchSize = 200,
        public readonly bool $verbose = false
    ) {
        $this->onQueue((string) config('order-recount.queue.scan', 'order-recount-scan'));
    }

    public function handle(PolicyRepository $repo): void
    {
        $sent = 0;
        $bucket = [];

        $flush = function () use (&$bucket, &$sent): void {
            if ($bucket === []) return;

            RecountTasksBatchJob::dispatch($bucket, 'cron', $this->verbose)
                ->onQueue((string) config('order-recount.queue.recount', 'order-recount'));

            $sent += count($bucket);
            $bucket = [];
        };

        $push = function (int $taskId) use (&$bucket, &$sent, $flush): void {
            if ($sent >= $this->maxTasks) return;

            if (!$this->dedupeAllow($taskId)) return;

            $bucket[] = $taskId;

            if (count($bucket) >= $this->batchSize) {
                $flush();
            }
        };

        // 1) policies candidates
        $statuses = $repo->cronStatuses();

        if ($statuses !== [] && $sent < $this->maxTasks) {
            Task::query()
                ->select(['id'])
                ->where('is_archive', 0)
                ->whereNull('completed_at')
                ->whereNull('deleted_at')
                ->whereIn('status', $statuses)
                ->chunkById($this->chunk, function ($rows) use (&$sent, $push): bool {
                    foreach ($rows as $r) {
                        if ($sent >= $this->maxTasks) return false;
                        $push((int)$r->id);
                    }
                    return true;
                });

            $flush();
        }

        if ($sent >= $this->maxTasks) return;

        // 2) floating directions
        $dirChunk     = (int) config('order-recount.scan.dir_chunk', 200);
        $dirTaskChunk = (int) config('order-recount.scan.dir_task_chunk', 200);

        DirectionExchange::query()
            ->select(['id', 'status', 'is_type_rate', 'floating_fee_time', 'floating_fee_statuses'])
            ->where('status', 1)
            ->where('is_type_rate', 1)
            ->chunkById($dirChunk, function ($dirs) use (&$sent, $push, $dirTaskChunk): void {
                foreach ($dirs as $dir) {
                    if ($sent >= $this->maxTasks) return;

                    $statuses = $dir->floating_fee_statuses ?? [];
                    $minutes  = (int) ($dir->floating_fee_time ?? 0);

                    if ($minutes <= 0 || !is_array($statuses) || $statuses === []) continue;

                    Task::query()
                        ->select(['id'])
                        ->where('is_archive', 0)
                        ->whereNull('completed_at')
                        ->whereNull('deleted_at')
                        ->where('floating_recount_stop', 0)
                        ->where('is_type_rate', 1)
                        ->where('type_rate', 1)
                        ->where('id_direction_exchange', (int)$dir->id)
                        ->whereIn('status', $statuses)
                        ->chunkById($dirTaskChunk, function ($rows) use (&$sent, $push): bool {
                            foreach ($rows as $r) {
                                if ($sent >= $this->maxTasks) return false;
                                $push((int)$r->id);
                            }
                            return true;
                        });
                }
            });

        $flush();
    }

    private function dedupeAllow(int $taskId): bool
    {
        $store = (string) config('order-recount.dedupe.store', 'redis');
        $ttl   = (int) config('order-recount.dedupe.ttl_seconds', 45);
        $pref  = (string) config('order-recount.dedupe.prefix', 'order-recount:dedupe');

        return Cache::store($store)->add("{$pref}:task:{$taskId}", 1, now()->addSeconds($ttl));
    }
}
