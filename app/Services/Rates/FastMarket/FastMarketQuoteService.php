<?php

declare(strict_types=1);

namespace App\Services\Rates\FastMarket;

use App\Models\BestChangeDirection;
use App\Models\DirectionExchange;
use Illuminate\Support\Facades\Log;
use iEXPackages\Calculator\CalculatorFacade;
use Throwable;

/**
 * Fast customer quote using shared market snapshot + canonical CalculatorFacade.
 *
 * For BESTCHANGE: compute rate_value from snapshot book (same primitives as
 * RatesUpdateService), hydrate BestChangeDirection in-memory, then run
 * CalculatorFacade — no second pricing formula, no request-path external fetch.
 */
final class FastMarketQuoteService
{
    public function __construct(
        private readonly MarketSnapshotStore $store,
        private readonly BestChangeBookRateComputer $bookRateComputer,
        private readonly FastMarketSourceRouter $router,
    ) {}

    /**
     * @return array{
     *   used_fast_path: bool,
     *   family: string,
     *   snapshot_id: ?string,
     *   market_age_seconds: ?int,
     *   freshness_state: ?string,
     *   calculated_at: int,
     *   rate: ?string,
     *   calculator_payload: ?array,
     *   fallback_used: bool,
     *   fallback_reason: ?string,
     *   error: ?string
     * }
     */
    public function quote(DirectionExchange $direction): array
    {
        $now = time();
        $route = $this->router->route($direction);
        $base = [
            'used_fast_path' => false,
            'family' => $route['family'],
            'snapshot_id' => null,
            'market_age_seconds' => null,
            'freshness_state' => null,
            'calculated_at' => $now,
            'rate' => null,
            'calculator_payload' => null,
            'fallback_used' => false,
            'fallback_reason' => null,
            'error' => null,
        ];

        if (! $route['use_bestchange_snapshot']) {
            return $this->persistedCalculatorQuote($direction, $base, 'family_not_bestchange_snapshot');
        }

        // Pair-addressable read: meta + one book. Never deserialize full rates_by_pair.
        $deId = (int) $direction->id;
        $materials = $this->store->getQuoteMaterials($deId);
        if ($materials === null) {
            return $this->persistedCalculatorQuote($direction, $base, 'snapshot_missing');
        }

        $snapshot = $materials['snapshot'];
        $pairKey = $materials['pair_key'];
        $rawRows = $materials['raw_rows'];

        $base['snapshot_id'] = $snapshot->snapshotId;
        $base['market_age_seconds'] = $snapshot->ageSeconds($now);
        $base['freshness_state'] = $snapshot->freshnessState($now);

        if (! $snapshot->isUsable($now)) {
            return $this->persistedCalculatorQuote($direction, $base, 'snapshot_expired');
        }

        $bc = $direction->bestchange_directions;
        if (! $bc instanceof BestChangeDirection || $pairKey === '' || $rawRows === []) {
            return $this->persistedCalculatorQuote($direction, $base, 'direction_not_in_snapshot');
        }

        $computed = $this->bookRateComputer->compute(
            direction: $bc,
            rawRows: $rawRows,
            pairKey: $pairKey,
            externalRates: $snapshot->externalRates,
            exchangersMap: $snapshot->exchangersMap,
        );
        if (! ($computed['ok'] ?? false) || ($computed['rate_value'] ?? null) === null) {
            return $this->persistedCalculatorQuote(
                $direction,
                $base,
                'book_compute_failed:'.($computed['error'] ?? $computed['skip'] ?? 'unknown')
            );
        }

        try {
            $hydrated = $this->hydrateDirectionForQuote($direction, (string) $computed['rate_value']);
            $payload = CalculatorFacade::setDirectionExchange($hydrated)->calculate()->toArray();
            $rate = (string) ($payload['rate'] ?? '');

            return array_merge($base, [
                'used_fast_path' => true,
                'rate' => $rate,
                'calculator_payload' => $payload,
            ]);
        } catch (Throwable $e) {
            Log::warning('fast_market_quote_calculator_failed', [
                'direction_id' => $deId,
                'message' => $e->getMessage(),
            ]);

            return $this->persistedCalculatorQuote($direction, $base, 'calculator_failed');
        }
    }

    /**
     * Hydrate an in-memory DirectionExchange from the current usable snapshot
     * for order calculateWithOptions parity with public fast quote.
     */
    public function hydrateDirectionFromSnapshot(DirectionExchange $direction): ?DirectionExchange
    {
        $route = $this->router->route($direction);
        if (! $route['use_bestchange_snapshot']) {
            return null;
        }
        $deId = (int) $direction->id;
        $materials = $this->store->getQuoteMaterials($deId);
        if ($materials === null) {
            return null;
        }
        $snapshot = $materials['snapshot'];
        if (! $snapshot->isUsable()) {
            return null;
        }
        $pairKey = $materials['pair_key'];
        $rawRows = $materials['raw_rows'];
        $bc = $direction->bestchange_directions;
        if (! $bc instanceof BestChangeDirection || $pairKey === '' || $rawRows === []) {
            return null;
        }
        $computed = $this->bookRateComputer->compute(
            direction: $bc,
            rawRows: $rawRows,
            pairKey: $pairKey,
            externalRates: $snapshot->externalRates,
            exchangersMap: $snapshot->exchangersMap,
        );
        if (! ($computed['ok'] ?? false) || ($computed['rate_value'] ?? null) === null) {
            return null;
        }

        return $this->hydrateDirectionForQuote($direction, (string) $computed['rate_value']);
    }

    /**
     * Shadow: compute fast candidate without changing authoritative output.
     *
     * @return array{authoritative: array, candidate: ?array, compare: array}
     */
    public function shadowCompare(DirectionExchange $direction): array
    {
        $authoritative = CalculatorFacade::setDirectionExchange($direction)->calculate()->toArray();
        $authRate = (string) ($authoritative['rate'] ?? '');

        $candidate = null;
        $compare = [
            'formula_diff' => null,
            'age_driven_diff_possible' => true,
            'auth_rate' => $authRate,
            'candidate_rate' => null,
            'rates_equal' => null,
        ];

        try {
            $candidate = $this->quote($direction);
            $candRate = (string) ($candidate['rate'] ?? '');
            $compare['candidate_rate'] = $candRate;
            $compare['rates_equal'] = $this->ratesEqual($authRate, $candRate);
            // Same-snapshot formula equivalence is proven offline; online shadow
            // diffs are expected when persisted rate_value is older than snapshot.
            $compare['formula_diff'] = null;
        } catch (Throwable $e) {
            $compare['error'] = $e->getMessage();
        }

        Log::info('fast_market_shadow_quote', [
            'direction_id' => (int) $direction->id,
            'auth_rate' => $authRate,
            'candidate_rate' => $compare['candidate_rate'],
            'rates_equal' => $compare['rates_equal'],
            'market_age' => $candidate['market_age_seconds'] ?? null,
            'snapshot_id' => $candidate['snapshot_id'] ?? null,
            'used_fast_path' => $candidate['used_fast_path'] ?? false,
        ]);

        return [
            'authoritative' => $authoritative,
            'candidate' => $candidate,
            'compare' => $compare,
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function persistedCalculatorQuote(DirectionExchange $direction, array $base, string $reason): array
    {
        if (! $this->persistedRateAgeAcceptable($direction)) {
            return array_merge($base, [
                'fallback_used' => false,
                'fallback_reason' => $reason.':persisted_too_old',
                'error' => 'no_usable_rate',
            ]);
        }

        try {
            $payload = CalculatorFacade::setDirectionExchange($direction)->calculate()->toArray();

            return array_merge($base, [
                'fallback_used' => true,
                'fallback_reason' => $reason,
                'rate' => (string) ($payload['rate'] ?? ''),
                'calculator_payload' => $payload,
            ]);
        } catch (Throwable $e) {
            return array_merge($base, [
                'fallback_used' => false,
                'fallback_reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function persistedRateAgeAcceptable(DirectionExchange $direction): bool
    {
        $bc = $direction->bestchange_directions;
        $ts = null;
        if ($bc instanceof BestChangeDirection && ! empty($bc->updated_at)) {
            $ts = $bc->updated_at->getTimestamp();
        } elseif (! empty($direction->updated_at)) {
            $ts = $direction->updated_at->getTimestamp();
        }
        if ($ts === null) {
            return true;
        }

        return (time() - $ts) <= FastMarketFlags::maxFallbackRateAgeSeconds();
    }

    private function hydrateDirectionForQuote(DirectionExchange $direction, string $rateValue): DirectionExchange
    {
        // Clone avoids leaking in-memory mutation into subsequent DB-backed reads
        // in the same request if the model is reused.
        $clone = clone $direction;
        $bc = $direction->bestchange_directions;
        if ($bc instanceof BestChangeDirection) {
            $bcClone = clone $bc;
            $bcClone->rate_value = $rateValue;
            $clone->setRelation('bestchange_directions', $bcClone);
        }

        return $clone;
    }

    private function ratesEqual(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }
        if (function_exists('bccomp')) {
            return bccomp($a, $b, 12) === 0;
        }

        return abs((float) $a - (float) $b) < 1e-12;
    }
}
