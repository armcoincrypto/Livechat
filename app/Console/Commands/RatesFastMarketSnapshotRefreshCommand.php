<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rates\FastMarket\BestChangeMarketSnapshotProducer;
use App\Services\Rates\FastMarket\FastMarketFlags;
use Illuminate\Console\Command;

/**
 * Refresh shared BestChange market snapshot for the fast quote plane.
 *
 * Does not run DirectionExchangeRecalculateService / full catalog persistence.
 */
final class RatesFastMarketSnapshotRefreshCommand extends Command
{
    protected $signature = 'rates:fast-market-snapshot-refresh
        {--force : Refresh even when FAST_MARKET_PLANE_ENABLED is false (ops/test only)}
        {--format=text : text|json}';

    protected $description = 'Refresh BestChange fast-market snapshot (no full compiler / no direction persist)';

    public function handle(BestChangeMarketSnapshotProducer $producer): int
    {
        $force = (bool) $this->option('force');
        if (! FastMarketFlags::planeEnabled() && ! $force) {
            $this->warn('FAST_MARKET_PLANE_ENABLED=false — skipped (use --force for ops)');

            return self::SUCCESS;
        }

        $result = $producer->refresh($force);
        $payload = [
            'ok' => $result['ok'],
            'skipped' => $result['skipped'],
            'error' => $result['error'],
            'snapshot_id' => $result['snapshot']?->snapshotId,
            'pair_count' => $result['snapshot']?->pairCount,
            'mapped_directions' => $result['snapshot']?->mappedDirectionCount,
            'fetch_ms' => $result['snapshot']?->fetchMs,
            'build_ms' => $result['snapshot']?->buildMs,
            'age_seconds' => $result['snapshot']?->ageSeconds(),
            'freshness_state' => $result['snapshot']?->freshnessState(),
        ];

        if ($this->option('format') === 'json') {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES));
        } else {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return ($result['ok'] || $result['skipped']) ? self::SUCCESS : self::FAILURE;
    }
}
