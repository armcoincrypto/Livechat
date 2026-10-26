<?php

declare(strict_types=1);

namespace App\Services\Rates\FastMarket;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Atomic shared snapshot store for PHP-FPM / queue / artisan consumers.
 *
 * Uses Redis cache when available (Cache::store('redis')), otherwise default
 * cache. Write is a single put of a fully-built payload — no partial books.
 */
final class MarketSnapshotStore
{
    public const CACHE_KEY = 'exswaping:fast_market:bestchange_snapshot_v1';

    public const LOCK_KEY = 'exswaping:fast_market:bestchange_snapshot_refresh_lock';

    public function __construct(
        private readonly ?Repository $cache = null,
    ) {}

    public function put(MarketSnapshot $snapshot): void
    {
        $payload = $snapshot->toArray();
        $ttl = max(120, FastMarketFlags::expiredThresholdSeconds() * 2);
        $this->repo()->put(self::CACHE_KEY, $payload, $ttl);
    }

    public function get(): ?MarketSnapshot
    {
        try {
            $raw = $this->repo()->get(self::CACHE_KEY);
        } catch (Throwable $e) {
            Log::warning('fast_market_snapshot_read_failed', ['message' => $e->getMessage()]);

            return null;
        }
        if (! is_array($raw) || ($raw['snapshot_id'] ?? '') === '') {
            return null;
        }

        try {
            return MarketSnapshot::fromArray($raw);
        } catch (Throwable $e) {
            Log::warning('fast_market_snapshot_decode_failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    public function clear(): void
    {
        $this->repo()->forget(self::CACHE_KEY);
    }

    private function repo(): Repository
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        try {
            return Cache::store('redis');
        } catch (Throwable) {
            return Cache::store();
        }
    }
}
