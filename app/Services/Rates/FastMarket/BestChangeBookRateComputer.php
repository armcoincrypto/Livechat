<?php

declare(strict_types=1);

namespace App\Services\Rates\FastMarket;

use App\Models\BestChangeDirection;
use App\Services\Rates\PeerRateSelector;
use App\Services\Rates\RateSanityGuard;
use App\Settings\BestChangeConfig;
use iEXPackages\BestChange\DTO\ComputedRate;
use iEXPackages\BestChange\DTO\PreparedMarket;
use iEXPackages\BestChange\DTO\RateSelectionPolicy;
use iEXPackages\BestChange\Services\BestChangeMarketPipeline;
use iEXPackages\BestChange\Services\RateCalculator;
use iEXPackages\BestChange\Services\RateSelectionPolicyResolver;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Canonical BestChange market→rate_value computer for a single direction.
 *
 * Reuses MarketPipeline + RateCalculator + RateSanityGuard/PeerRateSelector —
 * the same primitives RatesUpdateService uses when writing
 * bestchange_directions.rate_value. Does NOT persist.
 */
final class BestChangeBookRateComputer
{
    public function __construct(
        private readonly BestChangeConfig $settings,
        private readonly RateSelectionPolicyResolver $policyResolver,
        private readonly BestChangeMarketPipeline $marketPipeline,
        private readonly RateCalculator $calculator,
        private readonly RateSanityGuard $rateSanityGuard = new RateSanityGuard(),
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rawRows
     * @param  array<string, string>  $externalRates
     * @param  array<int, string>  $exchangersMap
     * @return array{
     *   ok: bool,
     *   rate_value: ?string,
     *   rate_value_without_step: ?string,
     *   source_name: ?string,
     *   skip: ?string,
     *   error: ?string
     * }
     */
    public function compute(
        BestChangeDirection $direction,
        array $rawRows,
        string $pairKey,
        array $externalRates,
        array $exchangersMap,
    ): array {
        $fail = static fn (?string $skip, ?string $error): array => [
            'ok' => false,
            'rate_value' => null,
            'rate_value_without_step' => null,
            'source_name' => null,
            'skip' => $skip,
            'error' => $error,
        ];

        try {
            $policy = $this->policyResolver->resolve($direction, $this->settings);
            $defaultPosition = max(1, (int) $this->settings->position());
            $globalBlacklist = $this->settings->blacklist();

            $market = $this->marketPipeline->prepare(
                $direction,
                $rawRows,
                $policy,
                $defaultPosition,
                $globalBlacklist,
                $pairKey,
                false,
                0,
            );

            if (($market->skipReason ?? null) === 'position_insufficient_depth') {
                return $fail('position_insufficient_depth', null);
            }
            if ($market->filteredRows === []) {
                return $fail(null, 'empty_filtered_rows');
            }
            if ($market->selectedRow === null) {
                return $fail(null, 'no_selected_row');
            }

            $computed = $this->calculator->calculate(
                direction: $direction,
                selectedRow: $market->selectedRow,
                sortedRows: $market->sortedRows,
                policy: $policy,
                externalRates: $externalRates,
                exchangersMap: $exchangersMap,
            );
            if ($computed === null) {
                return $fail(null, 'rate_calculator_null');
            }

            $computed = $this->applySanityGuard($direction, $computed, $market, $policy);
            if ($computed === null) {
                return $fail(null, 'rate_sanity_rejected');
            }

            return [
                'ok' => true,
                'rate_value' => (string) $computed->rateValue,
                'rate_value_without_step' => (string) ($computed->rateValueWithoutStep ?? '0'),
                'source_name' => (string) ($computed->sourceName ?? ''),
                'skip' => null,
                'error' => null,
            ];
        } catch (Throwable $e) {
            return $fail(null, $e->getMessage());
        }
    }

    private function applySanityGuard(
        BestChangeDirection $direction,
        ComputedRate $computed,
        PreparedMarket $market,
        RateSelectionPolicy $policy,
    ): ?ComputedRate {
        $peers = $this->rateSanityGuard->peerRatesFromBestChangeRows(
            $market->filteredRows !== [] ? $market->filteredRows : $market->sortedRows,
            $policy->typeField
        );

        $maxDeviation = (string) (iEXSetting(
            'rate_sanity_max_deviation_fraction',
            RateSanityGuard::DEFAULT_MAX_DEVIATION_FRACTION
        ) ?: RateSanityGuard::DEFAULT_MAX_DEVIATION_FRACTION);

        $selector = new PeerRateSelector($this->rateSanityGuard);
        $selector->selectValidPeerRate(
            peerRates: $peers,
            preferred: $computed->rateValue,
            maxMedianDeviation: $maxDeviation,
            minSample: 3,
        );

        $eval = $this->rateSanityGuard->evaluate(
            candidate: $computed->rateValue,
            peerRates: $peers,
            maxDeviationFraction: $maxDeviation,
            clamp: true
        );

        if ($eval['action'] === 'pass') {
            return $computed;
        }

        Log::warning('fast_market_rate_sanity_adjusted', [
            'bestchange_direction_id' => $direction->id ?? null,
            'direction_exchange_id' => $direction->id_direction_exchange ?? null,
            'action' => $eval['action'],
            'reason' => $eval['reason'],
        ]);

        if ($eval['action'] === 'reject' || ! ($eval['ok'] ?? false)) {
            return null;
        }

        return new ComputedRate(
            rateValue: $eval['effective'],
            rateValueWithoutStep: $computed->rateValueWithoutStep,
            sourceName: $computed->sourceName.'+sanity',
        );
    }
}
