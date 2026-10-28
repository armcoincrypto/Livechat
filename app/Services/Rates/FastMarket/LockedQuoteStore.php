<?php

declare(strict_types=1);

namespace App\Services\Rates\FastMarket;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Short-lived Redis-backed immutable quote artifacts.
 *
 * Key: exswaping:fast_market:locked_quote:{token}
 * Generation (emergency invalidate): exswaping:fast_market:locked_quote_generation
 */
final class LockedQuoteStore
{
    public const KEY_PREFIX = 'exswaping:fast_market:locked_quote:';

    public const GENERATION_KEY = 'exswaping:fast_market:locked_quote_generation';

    public function __construct(
        private readonly ?Repository $cache = null,
    ) {}

    public function put(LockedQuoteRecord $record, int $ttlSeconds): void
    {
        $ttl = max(5, $ttlSeconds);
        try {
            $ok = $this->repo()->put($this->key($record->quoteId), $record->toArray(), $ttl);
            if ($ok === false) {
                throw LockedQuoteException::redisUnavailable();
            }
        } catch (LockedQuoteException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('locked_quote_put_failed', ['message' => $e->getMessage()]);
            throw LockedQuoteException::redisUnavailable();
        }
    }

    public function get(string $quoteId): ?LockedQuoteRecord
    {
        try {
            $raw = $this->repo()->get($this->key($quoteId));
        } catch (Throwable $e) {
            Log::warning('locked_quote_get_failed', ['message' => $e->getMessage()]);
            throw LockedQuoteException::redisUnavailable();
        }
        if (! is_array($raw)) {
            return null;
        }

        return LockedQuoteRecord::fromArray($raw);
    }

    public function replace(LockedQuoteRecord $record, int $remainingTtlSeconds): void
    {
        $ttl = max(1, $remainingTtlSeconds);
        try {
            $this->repo()->put($this->key($record->quoteId), $record->toArray(), $ttl);
        } catch (Throwable $e) {
            Log::warning('locked_quote_replace_failed', ['message' => $e->getMessage()]);
            throw LockedQuoteException::redisUnavailable();
        }
    }

    public function currentGeneration(): int
    {
        try {
            $v = $this->repo()->get(self::GENERATION_KEY);

            return is_numeric($v) ? (int) $v : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Emergency invalidation: bump generation so all prior quotes fail closed.
     */
    public function bumpGeneration(): int
    {
        try {
            $next = $this->currentGeneration() + 1;
            $this->repo()->forever(self::GENERATION_KEY, $next);

            return $next;
        } catch (Throwable $e) {
            Log::warning('locked_quote_generation_bump_failed', ['message' => $e->getMessage()]);
            throw LockedQuoteException::redisUnavailable();
        }
    }

    private function key(string $quoteId): string
    {
        return self::KEY_PREFIX.$quoteId;
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
