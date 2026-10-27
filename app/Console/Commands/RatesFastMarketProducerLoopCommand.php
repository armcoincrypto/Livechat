<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rates\FastMarket\BestChangeMarketSnapshotProducer;
use App\Services\Rates\FastMarket\FastMarketFlags;
use App\Services\Rates\FastMarket\FastMarketProducerHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Long-lived ~30s BestChange market snapshot producer.
 *
 * Monotonic boundary scheduling: targets wall-clock multiples of the cadence
 * so a 8s fetch does not become "8s + sleep 30s". Single-flight is enforced
 * inside BestChangeMarketSnapshotProducer (Cache lock).
 *
 * Disabled when FAST_MARKET_PLANE_ENABLED is false (idle sleep). No DB writes.
 */
final class RatesFastMarketProducerLoopCommand extends Command
{
    protected $signature = 'rates:fast-market-producer-loop
        {--once : Run a single refresh cycle then exit}
        {--interval= : Override cadence seconds (default from config)}';

    protected $description = 'Daemon loop: refresh shared BestChange fast-market snapshot ~every 30s';

    public function handle(BestChangeMarketSnapshotProducer $producer): int
    {
        $once = (bool) $this->option('once');
        $intervalOpt = $this->option('interval');
        $interval = is_numeric($intervalOpt)
            ? max(15, min(60, (int) $intervalOpt))
            : FastMarketFlags::snapshotRefreshSeconds();

        $this->info(sprintf(
            'fast_market_producer_loop start interval=%ds once=%s plane=%s',
            $interval,
            $once ? '1' : '0',
            FastMarketFlags::planeEnabled() ? '1' : '0'
        ));

        $consecutiveFailures = 0;

        do {
            $cycleStart = microtime(true);
            $now = time();

            if (! FastMarketFlags::planeEnabled()) {
                FastMarketProducerHealth::record([
                    'state' => 'idle_plane_disabled',
                    'last_attempt_at' => $now,
                    'consecutive_failures' => $consecutiveFailures,
                ]);
                if ($once) {
                    return self::SUCCESS;
                }
                $this->sleepUntilBoundary($now, $interval);

                continue;
            }

            FastMarketProducerHealth::record([
                'state' => 'running',
                'last_attempt_at' => $now,
            ]);

            try {
                $result = $producer->refresh(false);
                $publishMs = (int) round((microtime(true) - $cycleStart) * 1000);
                $snap = $result['snapshot'] ?? null;

                if ($result['skipped'] && ($result['error'] ?? null) === 'refresh_in_progress') {
                    FastMarketProducerHealth::record([
                        'state' => 'lock_skipped',
                        'last_attempt_at' => $now,
                        'last_error_class' => 'refresh_in_progress',
                        'publish_ms' => $publishMs,
                        'consecutive_failures' => $consecutiveFailures,
                        'snapshot_id' => $snap?->snapshotId,
                        'snapshot_age' => $snap?->ageSeconds(),
                    ]);
                } elseif ($result['ok'] && $snap !== null) {
                    $consecutiveFailures = 0;
                    FastMarketProducerHealth::record([
                        'state' => 'ok',
                        'last_attempt_at' => $now,
                        'last_success_at' => $now,
                        'snapshot_id' => $snap->snapshotId,
                        'snapshot_age' => $snap->ageSeconds(),
                        'fetch_ms' => $snap->fetchMs,
                        'build_ms' => $snap->buildMs,
                        'publish_ms' => $publishMs,
                        'pair_count' => $snap->pairCount,
                        'consecutive_failures' => 0,
                        'last_error_class' => null,
                    ]);
                    Log::info('fast_market_producer_cycle_ok', [
                        'snapshot_id' => $snap->snapshotId,
                        'fetch_ms' => $snap->fetchMs,
                        'build_ms' => $snap->buildMs,
                        'publish_ms' => $publishMs,
                    ]);
                } else {
                    $consecutiveFailures++;
                    $err = (string) ($result['error'] ?? 'unknown');
                    FastMarketProducerHealth::record([
                        'state' => 'error',
                        'last_attempt_at' => $now,
                        'last_error_class' => $err,
                        'consecutive_failures' => $consecutiveFailures,
                        'publish_ms' => $publishMs,
                        'snapshot_id' => $snap?->snapshotId,
                        'snapshot_age' => $snap?->ageSeconds(),
                    ]);
                    Log::warning('fast_market_producer_cycle_failed', [
                        'error' => $err,
                        'consecutive_failures' => $consecutiveFailures,
                    ]);
                    if (! $once) {
                        $backoff = min(
                            FastMarketFlags::failureBackoffMaxSeconds(),
                            FastMarketFlags::failureBackoffSeconds() * max(1, $consecutiveFailures)
                        );
                        sleep($backoff);

                        continue;
                    }
                }
            } catch (Throwable $e) {
                $consecutiveFailures++;
                FastMarketProducerHealth::record([
                    'state' => 'exception',
                    'last_attempt_at' => $now,
                    'last_error_class' => class_basename($e),
                    'consecutive_failures' => $consecutiveFailures,
                ]);
                Log::error('fast_market_producer_cycle_exception', [
                    'message' => $e->getMessage(),
                    'consecutive_failures' => $consecutiveFailures,
                ]);
                if (! $once) {
                    $backoff = min(
                        FastMarketFlags::failureBackoffMaxSeconds(),
                        FastMarketFlags::failureBackoffSeconds() * max(1, $consecutiveFailures)
                    );
                    sleep($backoff);

                    continue;
                }

                return self::FAILURE;
            }

            if ($once) {
                return self::SUCCESS;
            }

            $this->sleepUntilBoundary(time(), $interval);
        } while (true);
    }

    /**
     * Sleep until the next monotonic cadence boundary (epoch % interval == 0),
     * with a minimum 1s sleep to avoid busy loops if the cycle overruns.
     */
    private function sleepUntilBoundary(int $now, int $interval): void
    {
        $next = (intdiv($now, $interval) + 1) * $interval;
        $sleep = max(1, $next - $now);
        sleep($sleep);
    }
}
