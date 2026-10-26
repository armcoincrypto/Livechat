<?php

declare(strict_types=1);

namespace App\Services\Rates\FastMarket;

/**
 * Immutable BestChange market snapshot shared by fast quote + reconciliation.
 *
 * Does not contain customer PII. Books are raw BestChange /rates rows keyed by
 * pair (same shape as RatesUpdateService / L1 generation context).
 */
final class MarketSnapshot
{
    public const SOURCE_BESTCHANGE = 'bestchange';

    public const STATE_FRESH = 'FRESH';

    public const STATE_STALE_BUT_USABLE = 'STALE_BUT_USABLE';

    public const STATE_EXPIRED = 'EXPIRED';

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $ratesByPair
     * @param  array<int, string>  $pairByDirectionExchangeId  de.id => pairKey
     * @param  array<int, int>  $bcIdByDirectionExchangeId  de.id => bestchange_directions.id
     * @param  array<string, string>  $externalRates
     * @param  array<int, string>  $exchangersMap
     */
    public function __construct(
        public readonly string $source,
        public readonly string $snapshotId,
        public readonly int $sourceTimestamp,
        public readonly int $fetchedAt,
        public readonly int $normalizedAt,
        public readonly int $expiresAt,
        public readonly string $fingerprint,
        public readonly array $ratesByPair,
        public readonly array $pairByDirectionExchangeId,
        public readonly array $bcIdByDirectionExchangeId,
        public readonly array $externalRates,
        public readonly array $exchangersMap,
        public readonly int $pairCount,
        public readonly int $mappedDirectionCount,
        public readonly int $fetchMs,
        public readonly int $buildMs,
    ) {}

    public function ageSeconds(?int $now = null): int
    {
        $now ??= time();

        return max(0, $now - $this->fetchedAt);
    }

    public function freshnessState(?int $now = null): string
    {
        $age = $this->ageSeconds($now);
        if ($age <= FastMarketFlags::freshnessThresholdSeconds()) {
            return self::STATE_FRESH;
        }
        if ($age <= FastMarketFlags::graceThresholdSeconds()) {
            return self::STATE_STALE_BUT_USABLE;
        }

        return self::STATE_EXPIRED;
    }

    public function isUsable(?int $now = null): bool
    {
        $state = $this->freshnessState($now);

        return $state === self::STATE_FRESH || $state === self::STATE_STALE_BUT_USABLE;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function rawRowsForDirectionExchange(int $directionExchangeId): ?array
    {
        if ($directionExchangeId <= 0) {
            return null;
        }
        $pairKey = $this->pairByDirectionExchangeId[$directionExchangeId] ?? null;
        if ($pairKey === null) {
            return null;
        }

        return $this->ratesByPair[$pairKey] ?? null;
    }

    public function pairKeyForDirectionExchange(int $directionExchangeId): ?string
    {
        return $this->pairByDirectionExchangeId[$directionExchangeId] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'snapshot_id' => $this->snapshotId,
            'source_timestamp' => $this->sourceTimestamp,
            'fetched_at' => $this->fetchedAt,
            'normalized_at' => $this->normalizedAt,
            'expires_at' => $this->expiresAt,
            'fingerprint' => $this->fingerprint,
            'rates_by_pair' => $this->ratesByPair,
            'pair_by_direction_exchange_id' => $this->pairByDirectionExchangeId,
            'bc_id_by_direction_exchange_id' => $this->bcIdByDirectionExchangeId,
            'external_rates' => $this->externalRates,
            'exchangers_map' => $this->exchangersMap,
            'pair_count' => $this->pairCount,
            'mapped_direction_count' => $this->mappedDirectionCount,
            'fetch_ms' => $this->fetchMs,
            'build_ms' => $this->buildMs,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            source: (string) ($data['source'] ?? self::SOURCE_BESTCHANGE),
            snapshotId: (string) ($data['snapshot_id'] ?? ''),
            sourceTimestamp: (int) ($data['source_timestamp'] ?? 0),
            fetchedAt: (int) ($data['fetched_at'] ?? 0),
            normalizedAt: (int) ($data['normalized_at'] ?? 0),
            expiresAt: (int) ($data['expires_at'] ?? 0),
            fingerprint: (string) ($data['fingerprint'] ?? ''),
            ratesByPair: is_array($data['rates_by_pair'] ?? null) ? $data['rates_by_pair'] : [],
            pairByDirectionExchangeId: self::intStringMap($data['pair_by_direction_exchange_id'] ?? []),
            bcIdByDirectionExchangeId: self::intIntMap($data['bc_id_by_direction_exchange_id'] ?? []),
            externalRates: is_array($data['external_rates'] ?? null) ? $data['external_rates'] : [],
            exchangersMap: self::intStringMap($data['exchangers_map'] ?? []),
            pairCount: (int) ($data['pair_count'] ?? 0),
            mappedDirectionCount: (int) ($data['mapped_direction_count'] ?? 0),
            fetchMs: (int) ($data['fetch_ms'] ?? 0),
            buildMs: (int) ($data['build_ms'] ?? 0),
        );
    }

    /**
     * @param  mixed  $raw
     * @return array<int, string>
     */
    private static function intStringMap(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $k => $v) {
            $out[(int) $k] = (string) $v;
        }

        return $out;
    }

    /**
     * @param  mixed  $raw
     * @return array<int, int>
     */
    private static function intIntMap(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $k => $v) {
            $out[(int) $k] = (int) $v;
        }

        return $out;
    }
}
