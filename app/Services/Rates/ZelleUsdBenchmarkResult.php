<?php

declare(strict_types=1);

namespace App\Services\Rates;

/**
 * Result of resolving the USDTTRC20→destination benchmark for a ZELLEUSD→destination direction.
 */
final class ZelleUsdBenchmarkResult
{
    public const REASON_FOUND = 'ZELLE_BENCHMARK_FOUND';
    public const REASON_MISSING = 'ZELLE_BENCHMARK_MISSING';
    public const REASON_AMBIGUOUS = 'ZELLE_BENCHMARK_AMBIGUOUS';
    public const REASON_ZERO = 'ZELLE_BENCHMARK_ZERO';
    public const REASON_STALE = 'ZELLE_BENCHMARK_STALE';
    public const REASON_RETIRED = 'ZELLE_BENCHMARK_RETIRED';
    public const REASON_NOT_QUOTEABLE = 'ZELLE_BENCHMARK_NOT_QUOTEABLE';
    public const REASON_SOURCE_ERROR = 'ZELLE_BENCHMARK_SOURCE_ERROR';
    public const REASON_NOT_ZELLE_OUTGOING = 'ZELLE_NOT_OUTGOING';
    public const REASON_STABLE_PEG = 'ZELLE_BENCHMARK_FOUND';

    public function __construct(
        public readonly bool $eligible,
        public readonly int $zelleDirectionId,
        public readonly ?int $destinationCurrencyId,
        public readonly ?int $benchmarkDirectionId,
        public readonly ?string $benchmarkRate,
        public readonly ?string $benchmarkTimestamp,
        public readonly ?string $benchmarkSource,
        public readonly bool $fresh,
        public readonly string $reasonCode,
        public readonly string $matchMethod = 'currency_id_exact',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'eligible' => $this->eligible,
            'zelle_direction_id' => $this->zelleDirectionId,
            'destination_currency_id' => $this->destinationCurrencyId,
            'benchmark_direction_id' => $this->benchmarkDirectionId,
            'benchmark_rate' => $this->benchmarkRate,
            'benchmark_timestamp' => $this->benchmarkTimestamp,
            'benchmark_source' => $this->benchmarkSource,
            'fresh' => $this->fresh,
            'reason_code' => $this->reasonCode,
            'match_method' => $this->matchMethod,
        ];
    }
}
