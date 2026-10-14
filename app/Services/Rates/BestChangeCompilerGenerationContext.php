<?php

declare(strict_types=1);

namespace App\Services\Rates;

/**
 * In-memory market books for one compiler:bestchange generation.
 *
 * Populated by RatesUpdateService after fetchRatesBatch. Consumed by L1 shadow
 * Top-N reuse (never authoritative in this task). Cleared when generation ends.
 */
final class BestChangeCompilerGenerationContext
{
    private bool $active = false;

    private int $startedAtMs = 0;

    /** @var array<string, array<int, array<string, mixed>>> pairKey => rawRows */
    private array $ratesByPair = [];

    /** @var array<int, string> direction_exchange_id => pairKey */
    private array $pairByDirectionExchangeId = [];

    /** @var array<int, int> direction_exchange_id => bestchange_directions.id */
    private array $bcIdByDirectionExchangeId = [];

    public function beginGeneration(): void
    {
        $this->active = true;
        $this->startedAtMs = (int) floor(microtime(true) * 1000);
        $this->ratesByPair = [];
        $this->pairByDirectionExchangeId = [];
        $this->bcIdByDirectionExchangeId = [];
    }

    public function endGeneration(): void
    {
        $this->active = false;
        $this->ratesByPair = [];
        $this->pairByDirectionExchangeId = [];
        $this->bcIdByDirectionExchangeId = [];
        $this->startedAtMs = 0;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function startedAtMs(): int
    {
        return $this->startedAtMs;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $ratesMap
     * @param  array<int, string>  $bcDirectionIdToPairKey  bestchange_directions.id => pairKey
     * @param  array<int, int>  $bcDirectionIdToDeId  bestchange_directions.id => direction_exchange.id
     */
    public function putBatchBooks(array $ratesMap, array $bcDirectionIdToPairKey, array $bcDirectionIdToDeId): void
    {
        if (! $this->active) {
            return;
        }

        foreach ($ratesMap as $pairKey => $rows) {
            if (! is_string($pairKey) || $pairKey === '') {
                continue;
            }
            $this->ratesByPair[$pairKey] = is_array($rows) ? array_values($rows) : [];
        }

        foreach ($bcDirectionIdToPairKey as $bcId => $pairKey) {
            $bcId = (int) $bcId;
            $deId = (int) ($bcDirectionIdToDeId[$bcId] ?? 0);
            if ($bcId <= 0 || $deId <= 0 || ! is_string($pairKey) || $pairKey === '') {
                continue;
            }
            $this->pairByDirectionExchangeId[$deId] = $pairKey;
            $this->bcIdByDirectionExchangeId[$deId] = $bcId;
        }
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function rawRowsForDirectionExchange(int $directionExchangeId): ?array
    {
        if (! $this->active || $directionExchangeId <= 0) {
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

    public function pairCount(): int
    {
        return count($this->ratesByPair);
    }

    public function mappedDirectionCount(): int
    {
        return count($this->pairByDirectionExchangeId);
    }
}
