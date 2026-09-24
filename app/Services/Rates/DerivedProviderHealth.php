<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Services\Rates\ProviderIntegrity\DerivedLegLastKnownGoodStore;
use App\Services\Rates\ProviderIntegrity\ProviderLegAggregator;

/**
 * Runtime health surface for derived critical legs (never HEALTHY on BREAK-only data).
 */
final class DerivedProviderHealth
{
    public function __construct(
        private readonly IndependentMarketBaseline $baseline = new IndependentMarketBaseline(),
        private readonly DerivedLegLastKnownGoodStore $lkg = new DerivedLegLastKnownGoodStore(),
    ) {
    }

    /**
     * @param list<string> $symbols
     * @return array<string,mixed>
     */
    public function snapshot(array $symbols = ['TONUSDT', 'BTCUSDT', 'ETHUSDT']): array
    {
        $legs = [];
        $degraded = [];
        $quarantined = [];
        foreach ($symbols as $sym) {
            $q = $this->baseline->quote($sym);
            $state = is_array($q)
                ? (string) ($q['quality_state'] ?? ProviderLegAggregator::QUALITY_HEALTHY)
                : ProviderLegAggregator::QUALITY_QUARANTINED;
            $entry = [
                'symbol' => $sym,
                'state' => $state,
                'rate' => is_array($q) ? ($q['rate'] ?? null) : null,
                'provider_set' => is_array($q) ? ($q['distinct_providers'] ?? []) : [],
                'provider_count' => is_array($q) ? (int) ($q['provider_count'] ?? 0) : 0,
                'source' => is_array($q) ? ($q['source'] ?? null) : null,
                'as_of' => is_array($q) ? ($q['as_of'] ?? null) : null,
                'age_seconds' => is_array($q) ? ($q['age_seconds'] ?? null) : null,
                'excluded_count' => is_array($q) ? count($q['excluded'] ?? []) : 0,
            ];
            if ($state === ProviderLegAggregator::QUALITY_QUARANTINED || $q === null) {
                $lkg = $this->lkg->getFresh($sym, IndependentMarketBaseline::CRYPTO_MAX_AGE_SECONDS * 4);
                $entry['lkg'] = $lkg;
                $quarantined[] = $sym;
            } elseif (str_starts_with($state, 'DEGRADED')) {
                $degraded[] = $sym;
            }
            $legs[$sym] = $entry;
        }

        $overall = ProviderLegAggregator::QUALITY_HEALTHY;
        if ($quarantined !== []) {
            $overall = ProviderLegAggregator::QUALITY_QUARANTINED;
        } elseif ($degraded !== []) {
            $overall = ProviderLegAggregator::QUALITY_DEGRADED_SINGLE;
        }

        return [
            'DERIVED_HEALTH' => $overall,
            'legs' => $legs,
            'degraded_legs' => $degraded,
            'quarantined_legs' => $quarantined,
            'generated_at' => gmdate('c'),
        ];
    }
}
