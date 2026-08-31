<?php

declare(strict_types=1);

namespace App\Services\Rates;

use Illuminate\Support\Facades\Cache;

/**
 * Marks a completed BestChange → direction_exchange generation.
 *
 * RatesConnection is ionCube-opaque: BC upsert and DE recalculate remain separate
 * commits inside that facade. This marker is written only AFTER both steps succeed
 * so operators/XML health can distinguish a coherent generation from a mid-cycle
 * window (RATE_UPDATE_ATOMIC=NO at row level, but generation-complete is observable).
 */
final class BestChangeRateGenerationMarker
{
    public const CACHE_KEY = 'iex:bc:rate_generation';

    /**
     * @param array<string, mixed> $meta
     * @return array{id: string, committed_at: string, unix: int, meta: array<string, mixed>}
     */
    public static function markCommitted(array $meta = []): array
    {
        $payload = [
            'id' => bin2hex(random_bytes(8)),
            'committed_at' => gmdate('c'),
            'unix' => time(),
            'meta' => $meta,
        ];

        Cache::put(self::CACHE_KEY, $payload, 86400);

        return $payload;
    }

    /**
     * @return array{id: string, committed_at: string, unix: int, meta: array<string, mixed>}|null
     */
    public static function current(): ?array
    {
        $raw = Cache::get(self::CACHE_KEY);
        if (!is_array($raw) || !isset($raw['id'], $raw['unix'])) {
            return null;
        }

        return [
            'id' => (string) $raw['id'],
            'committed_at' => (string) ($raw['committed_at'] ?? ''),
            'unix' => (int) $raw['unix'],
            'meta' => is_array($raw['meta'] ?? null) ? $raw['meta'] : [],
        ];
    }

    public static function ageSeconds(): ?int
    {
        $cur = self::current();
        if ($cur === null) {
            return null;
        }

        return max(0, time() - (int) $cur['unix']);
    }
}
