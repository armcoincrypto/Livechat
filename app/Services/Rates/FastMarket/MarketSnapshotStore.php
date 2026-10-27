<?php

declare(strict_types=1);

namespace App\Services\Rates\FastMarket;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Atomic shared snapshot store for PHP-FPM / queue / artisan consumers.
 *
 * Storage model (v2 — pair-addressable):
 *   current  → {snapshot_id, published_at}
 *   meta     → timings + DE maps + external_rates + exchangers_map (NO books)
 *   pairs    → Redis HASH field=pairKey value=JSON rows
 *
 * Quote path reads current → meta → HGET one pair. Full-book deserialize is
 * never required per request. Legacy monolithic CACHE_KEY is cleared on put
 * and only used as a last-resort migration read (avoided under request path).
 */
final class MarketSnapshotStore
{
    /** @deprecated Monolithic blob — OOM under 128M; retained for migration read only */
    public const CACHE_KEY = 'exswaping:fast_market:bestchange_snapshot_v1';

    public const LOCK_KEY = 'exswaping:fast_market:bestchange_snapshot_refresh_lock';

    public const CURRENT_KEY = 'exswaping:fast_market:snapshot:current_v2';

    public const MAX_RETAINED_VERSIONS = 2;

    public function __construct(
        private readonly ?Repository $cache = null,
    ) {}

    public function put(MarketSnapshot $snapshot): void
    {
        $ttl = max(120, FastMarketFlags::expiredThresholdSeconds() * 2);
        $id = $snapshot->snapshotId;
        if ($id === '') {
            throw new \InvalidArgumentException('snapshot_id required');
        }

        $prevId = $this->currentSnapshotId();

        $this->writePairsHash($id, $snapshot->ratesByPair, $ttl);
        $this->repo()->put($this->metaKey($id), $snapshot->toMetaArray(), $ttl);

        // Atomic pointer switch AFTER pair+meta are complete.
        $this->repo()->put(self::CURRENT_KEY, [
            'snapshot_id' => $id,
            'published_at' => time(),
            'pair_count' => $snapshot->pairCount,
            'schema' => 2,
        ], $ttl);

        // Remove OOM-prone monolithic blob so FPM never rediscovers it.
        try {
            $this->repo()->forget(self::CACHE_KEY);
        } catch (Throwable) {
        }

        $this->gcVersions($id, $prevId, $ttl);
    }

    /**
     * Request-path materials for one BestChange direction.
     *
     * @return array{
     *   snapshot: MarketSnapshot,
     *   pair_key: string,
     *   raw_rows: array<int, array<string, mixed>>
     * }|null
     */
    public function getQuoteMaterials(int $directionExchangeId): ?array
    {
        if ($directionExchangeId <= 0) {
            return null;
        }

        $meta = $this->getMeta();
        if ($meta === null) {
            return null;
        }

        $pairKey = $meta->pairKeyForDirectionExchange($directionExchangeId);
        if ($pairKey === null || $pairKey === '') {
            return null;
        }

        $rawRows = $this->getPairRows($meta->snapshotId, $pairKey);
        if ($rawRows === null) {
            return null;
        }

        return [
            'snapshot' => $meta,
            'pair_key' => $pairKey,
            'raw_rows' => $rawRows,
        ];
    }

    /**
     * Metadata-only snapshot (ratesByPair empty). Safe under 128M.
     */
    public function getMeta(): ?MarketSnapshot
    {
        try {
            $current = $this->repo()->get(self::CURRENT_KEY);
            if (! is_array($current) || ($current['snapshot_id'] ?? '') === '') {
                // Do NOT fall back to monolithic blob here — that path OOMs under 128M.
                return null;
            }
            $id = (string) $current['snapshot_id'];
            $raw = $this->repo()->get($this->metaKey($id));
            if (! is_array($raw) || ($raw['snapshot_id'] ?? '') === '') {
                return null;
            }

            return MarketSnapshot::fromMetaArray($raw);
        } catch (Throwable $e) {
            Log::warning('fast_market_snapshot_meta_read_failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function getPairRows(string $snapshotId, string $pairKey): ?array
    {
        if ($snapshotId === '' || $pairKey === '') {
            return null;
        }
        try {
            $raw = $this->redis()->hGet($this->pairsRedisKey($snapshotId), $pairKey);
            if (! is_string($raw) || $raw === '') {
                return null;
            }
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                Log::warning('fast_market_pair_decode_failed', [
                    'snapshot_id' => $snapshotId,
                    'pair_key' => $pairKey,
                ]);

                return null;
            }

            /** @var array<int, array<string, mixed>> $decoded */
            return $decoded;
        } catch (Throwable $e) {
            Log::warning('fast_market_pair_read_failed', [
                'snapshot_id' => $snapshotId,
                'pair_key' => $pairKey,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Full snapshot reconstruct (artisan/tests only). Avoid in FPM request path.
     */
    public function get(): ?MarketSnapshot
    {
        $meta = $this->getMeta();
        if ($meta === null) {
            return $this->legacyMonolithicFullFallback();
        }
        if ($meta->pairCount <= 0) {
            return $meta;
        }

        try {
            $all = $this->redis()->hGetAll($this->pairsRedisKey($meta->snapshotId));
            if (! is_array($all) || $all === []) {
                // Pointer+meta exist but pairs missing → treat as incomplete.
                return null;
            }
            $rates = [];
            foreach ($all as $pairKey => $json) {
                if (! is_string($json) || $json === '') {
                    continue;
                }
                $rows = json_decode($json, true);
                if (is_array($rows)) {
                    $rates[(string) $pairKey] = $rows;
                }
            }

            return $meta->withRatesByPair($rates);
        } catch (Throwable $e) {
            Log::warning('fast_market_snapshot_full_read_failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    public function clear(): void
    {
        $id = $this->currentSnapshotId();
        try {
            $this->repo()->forget(self::CURRENT_KEY);
            $this->repo()->forget(self::CACHE_KEY);
            if ($id !== null) {
                $this->repo()->forget($this->metaKey($id));
                $this->redis()->del($this->pairsRedisKey($id));
            }
        } catch (Throwable) {
        }
    }

    public function currentSnapshotId(): ?string
    {
        try {
            $current = $this->repo()->get(self::CURRENT_KEY);
            if (is_array($current) && ($current['snapshot_id'] ?? '') !== '') {
                return (string) $current['snapshot_id'];
            }
        } catch (Throwable) {
        }

        return null;
    }

    private function metaKey(string $snapshotId): string
    {
        return 'exswaping:fast_market:snapshot:'.$snapshotId.':meta_v2';
    }

    private function pairsRedisKey(string $snapshotId): string
    {
        // Redis::connection('cache') applies database.redis.options.prefix only.
        return 'exswaping:fast_market:snapshot:'.$snapshotId.':pairs_v2';
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $ratesByPair
     */
    private function writePairsHash(string $snapshotId, array $ratesByPair, int $ttl): void
    {
        $key = $this->pairsRedisKey($snapshotId);
        $redis = $this->redis();

        // Replace hash atomically for this version id (id is unique per publish).
        $redis->del($key);
        if ($ratesByPair === []) {
            $redis->expire($key, $ttl);

            return;
        }

        // Chunk HMSET to bound producer peak / pipeline size.
        $chunk = [];
        $n = 0;
        foreach ($ratesByPair as $pairKey => $rows) {
            $chunk[(string) $pairKey] = (string) json_encode($rows, JSON_UNESCAPED_UNICODE);
            $n++;
            if ($n >= 100) {
                $redis->hMSet($key, $chunk);
                $chunk = [];
                $n = 0;
            }
        }
        if ($chunk !== []) {
            $redis->hMSet($key, $chunk);
        }
        $redis->expire($key, $ttl);

        // Completeness guard before pointer switch (caller switches after this returns).
        $stored = (int) $redis->hLen($key);
        if ($stored < count($ratesByPair)) {
            throw new \RuntimeException(
                'fast_market_pair_hash_incomplete stored='.$stored.' expected='.count($ratesByPair)
            );
        }
    }

    private function gcVersions(string $currentId, ?string $previousId, int $ttl): void
    {
        // Retain current + previous for in-flight readers. Drop anything else
        // recorded in a small ring list.
        try {
            $ringKey = 'exswaping:fast_market:snapshot:version_ring_v2';
            $ring = $this->repo()->get($ringKey);
            $ids = is_array($ring) ? array_values(array_filter(array_map('strval', $ring))) : [];
            array_unshift($ids, $currentId);
            if ($previousId !== null && $previousId !== '' && $previousId !== $currentId) {
                $ids[] = $previousId;
            }
            $ids = array_values(array_unique($ids));
            $keep = array_slice($ids, 0, self::MAX_RETAINED_VERSIONS);
            $drop = array_slice($ids, self::MAX_RETAINED_VERSIONS);
            foreach ($drop as $oldId) {
                if ($oldId === $currentId || $oldId === $previousId) {
                    continue;
                }
                try {
                    $this->repo()->forget($this->metaKey($oldId));
                    $this->redis()->del($this->pairsRedisKey($oldId));
                } catch (Throwable) {
                }
            }
            $this->repo()->put($ringKey, $keep, max($ttl * 4, 3600));

            // Refresh TTL on retained previous so in-flight readers remain valid.
            if ($previousId !== null && $previousId !== '' && $previousId !== $currentId) {
                $prevMeta = $this->repo()->get($this->metaKey($previousId));
                if (is_array($prevMeta) && ($prevMeta['snapshot_id'] ?? '') !== '') {
                    $this->repo()->put($this->metaKey($previousId), $prevMeta, $ttl);
                }
                try {
                    $this->redis()->expire($this->pairsRedisKey($previousId), $ttl);
                } catch (Throwable) {
                }
            }
        } catch (Throwable $e) {
            Log::warning('fast_market_snapshot_gc_failed', ['message' => $e->getMessage()]);
        }
    }

    private function legacyMonolithicFullFallback(): ?MarketSnapshot
    {
        // Artisan-only escape hatch. Request path must use getMeta/getQuoteMaterials.
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

    /**
     * @return \Illuminate\Redis\Connections\Connection
     */
    private function redis()
    {
        $connection = (string) (config('cache.stores.redis.connection') ?: 'cache');

        return Redis::connection($connection);
    }
}
