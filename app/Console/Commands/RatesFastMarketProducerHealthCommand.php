<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rates\FastMarket\FastMarketFlags;
use App\Services\Rates\FastMarket\FastMarketProducerHealth;
use App\Services\Rates\FastMarket\MarketSnapshotStore;
use Illuminate\Console\Command;

final class RatesFastMarketProducerHealthCommand extends Command
{
    protected $signature = 'rates:fast-market-producer-health {--format=json : json|text}';

    protected $description = 'Show fast-market producer health + current snapshot age (no secrets)';

    public function handle(MarketSnapshotStore $store): int
    {
        $health = FastMarketProducerHealth::read() ?? [];
        $snap = $store->get();
        $payload = [
            'plane_enabled' => FastMarketFlags::planeEnabled(),
            'shadow_enabled' => FastMarketFlags::shadowEnabled(),
            'producer_daemon_preferred' => FastMarketFlags::producerDaemonPreferred(),
            'cadence_seconds' => FastMarketFlags::snapshotRefreshSeconds(),
            'producer' => $health,
            'snapshot' => $snap === null ? null : [
                'snapshot_id' => $snap->snapshotId,
                'fetched_at' => $snap->fetchedAt,
                'source_timestamp' => $snap->sourceTimestamp,
                'age_seconds' => $snap->ageSeconds(),
                'freshness_state' => $snap->freshnessState(),
                'pair_count' => $snap->pairCount,
                'fetch_ms' => $snap->fetchMs,
                'build_ms' => $snap->buildMs,
            ],
            // SNAPSHOT_AGE_SOURCE=fetched_at (BestChange batch has no reliable per-book source clock)
            'snapshot_age_source' => 'fetched_at',
            'source_timestamp_reliable' => false,
        ];

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
