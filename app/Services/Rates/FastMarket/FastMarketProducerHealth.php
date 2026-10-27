<?php

declare(strict_types=1);

namespace App\Services\Rates\FastMarket;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Producer health snapshot (no secrets) shared via Redis for ops/asserts.
 */
final class FastMarketProducerHealth
{
    public const CACHE_KEY = 'exswaping:fast_market:producer_health_v1';

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function record(array $fields): void
    {
        try {
            $prev = self::read();
            $merged = array_merge($prev ?? [], $fields, [
                'updated_at' => time(),
            ]);
            Cache::store('redis')->put(self::CACHE_KEY, $merged, 86400);
        } catch (Throwable) {
            // Health is best-effort; never break the producer path.
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function read(): ?array
    {
        try {
            $raw = Cache::store('redis')->get(self::CACHE_KEY);

            return is_array($raw) ? $raw : null;
        } catch (Throwable) {
            return null;
        }
    }
}
