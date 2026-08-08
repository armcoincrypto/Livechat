<?php

declare(strict_types=1);

namespace App\Services\Rates;

use App\Models\DirectionExchange;
use iEXPackages\Calculator\CalculatorFacade;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Single server authority for website / order / BestChange rates (Release A revised).
 *
 * Non-ZELLE formula: final = base × (1 + mode_fee_percent / 100)
 *   FLOATING → floating_fee; FIXED → fix_fee.
 *
 * ZELLEUSD outgoing (owner-approved):
 *   FLOATING → benchmark × (1 + floating_fee/100)
 *   FIXED    → benchmark × (1 + floating_fee/100) × (1 + fix_fee/100)
 *   XML      → always FLOATING (never includes the additional fix_fee step)
 *
 * Default public / order mode: FLOATING.
 */
final class CanonicalDirectionRateCalculator
{
    public const DEFAULT_MODE = RateMode::Floating;
    public const ROUNDING_SCALE = 18;

    public function __construct(
        private readonly RateFeePercentNormalizer $normalizer = new RateFeePercentNormalizer(),
    ) {
    }

    public static function make(): self
    {
        return new self();
    }

    public function calculate(
        DirectionExchange $direction,
        RateMode $mode = self::DEFAULT_MODE,
        RateChannel $channel = RateChannel::Website,
        ?string $baseRateOverride = null,
    ): CalculatedDirectionRate {
        $baseRate = $baseRateOverride;
        if ($baseRate === null) {
            $baseRate = $this->resolveBaseRate($direction);
        }

        $typeRateEnabled = (int) ($direction->is_type_rate ?? 0) === 1
            || $channel === RateChannel::Bestchange;

        // ZELLE FIXED compounds floating commercial adjustment then additional fix_fee.
        // Non-ZELLE FIXED remains fix_fee alone (platform-wide legacy semantics).
        if ($mode === RateMode::Fixed && $this->isZelleOutgoing($direction)) {
            return $this->calculateZelleFixed(
                $direction,
                (string) $baseRate,
                $channel,
                $typeRateEnabled,
            );
        }

        $rawFee = $mode === RateMode::Fixed
            ? ($direction->fix_fee ?? '0')
            : ($direction->floating_fee ?? '0');

        // When type_rate is disabled, still expose fixed/floating as base (0% fee)
        // for API symmetry. BestChange always uses floating_fee (see calculateForExport).
        if (!$typeRateEnabled) {
            $rawFee = '0';
        }

        $normalized = $this->normalizer->toPercentExpressionOrZero($rawFee);
        if (!$normalized['ok']) {
            Log::warning('canonical_rate_fee_malformed', [
                'direction_id' => $direction->id,
                'mode' => $mode->value,
                'channel' => $channel->value,
                'raw' => $rawFee,
                'classification' => $normalized['classification'],
                'reason' => $normalized['reason'],
            ]);
        }

        $final = $this->normalizer->applyPercentToRate(
            (string) $baseRate,
            $normalized['percent'],
            self::ROUNDING_SCALE
        );

        if (bccomp($final, '0', self::ROUNDING_SCALE) <= 0) {
            return new CalculatedDirectionRate(
                directionId: (int) $direction->id,
                baseRate: (string) $baseRate,
                mode: $mode,
                adjustmentValue: $normalized['percent'],
                adjustmentUnit: RateAdjustmentUnit::Percent,
                finalRate: '0',
                roundingScale: self::ROUNDING_SCALE,
                sourceTimestamp: $this->sourceTimestamp($direction),
                channel: $channel,
                eligible: false,
                reason: 'non_positive_final_rate',
                feeClassification: $normalized['classification'],
            );
        }

        return new CalculatedDirectionRate(
            directionId: (int) $direction->id,
            baseRate: (string) $baseRate,
            mode: $mode,
            adjustmentValue: $normalized['percent'],
            adjustmentUnit: RateAdjustmentUnit::Percent,
            finalRate: $final,
            roundingScale: self::ROUNDING_SCALE,
            sourceTimestamp: $this->sourceTimestamp($direction),
            channel: $channel,
            eligible: true,
            reason: $normalized['ok'] ? null : $normalized['reason'],
            feeClassification: $normalized['classification'],
        );
    }

    /**
     * ZELLEUSD FIXED: floating_fee first, then fix_fee once. No legacy profit/add_course.
     * adjustmentValue reports the additional fix_fee percent (admin field semantics).
     */
    private function calculateZelleFixed(
        DirectionExchange $direction,
        string $baseRate,
        RateChannel $channel,
        bool $typeRateEnabled,
    ): CalculatedDirectionRate {
        $floatRaw = $typeRateEnabled ? ($direction->floating_fee ?? '0') : '0';
        $fixRaw = $typeRateEnabled ? ($direction->fix_fee ?? '0') : '0';

        $floatNorm = $this->normalizer->toPercentExpressionOrZero($floatRaw);
        $fixNorm = $this->normalizer->toPercentExpressionOrZero($fixRaw);

        if (!$floatNorm['ok'] || !$fixNorm['ok']) {
            Log::warning('canonical_zelle_fixed_fee_malformed', [
                'direction_id' => $direction->id,
                'channel' => $channel->value,
                'floating_raw' => $floatRaw,
                'fix_raw' => $fixRaw,
                'floating_classification' => $floatNorm['classification'],
                'fix_classification' => $fixNorm['classification'],
            ]);
        }

        $afterFloating = $this->normalizer->applyPercentToRate(
            $baseRate,
            $floatNorm['percent'],
            self::ROUNDING_SCALE
        );
        $final = $this->normalizer->applyPercentToRate(
            $afterFloating,
            $fixNorm['percent'],
            self::ROUNDING_SCALE
        );

        $ok = $floatNorm['ok'] && $fixNorm['ok'];
        $classification = !$fixNorm['ok']
            ? $fixNorm['classification']
            : $floatNorm['classification'];

        if (bccomp($final, '0', self::ROUNDING_SCALE) <= 0) {
            return new CalculatedDirectionRate(
                directionId: (int) $direction->id,
                baseRate: $baseRate,
                mode: RateMode::Fixed,
                adjustmentValue: $fixNorm['percent'],
                adjustmentUnit: RateAdjustmentUnit::Percent,
                finalRate: '0',
                roundingScale: self::ROUNDING_SCALE,
                sourceTimestamp: $this->sourceTimestamp($direction),
                channel: $channel,
                eligible: false,
                reason: 'non_positive_final_rate',
                feeClassification: $classification,
            );
        }

        return new CalculatedDirectionRate(
            directionId: (int) $direction->id,
            baseRate: $baseRate,
            mode: RateMode::Fixed,
            adjustmentValue: $fixNorm['percent'],
            adjustmentUnit: RateAdjustmentUnit::Percent,
            finalRate: $final,
            roundingScale: self::ROUNDING_SCALE,
            sourceTimestamp: $this->sourceTimestamp($direction),
            channel: $channel,
            eligible: true,
            reason: $ok ? null : ($fixNorm['reason'] ?? $floatNorm['reason']),
            feeClassification: $classification,
        );
    }

    private function isZelleOutgoing(DirectionExchange $direction): bool
    {
        return (int) ($direction->id_currency1 ?? 0) === ZelleUsdBenchmarkResolver::ZELLE_CURRENCY_ID;
    }

    /**
     * BestChange XML always exports the canonical FLOATING rate.
     * file_rate_source remains an eligibility gate in the exporter; it does not select fix_fee.
     */
    public function calculateForExport(DirectionExchange $direction, ?string $baseRateOverride = null): CalculatedDirectionRate
    {
        return $this->calculate(
            $direction,
            RateMode::Floating,
            RateChannel::Bestchange,
            $baseRateOverride,
        );
    }

    /**
     * API course enrichment: base + fixed + floating + display(default floating).
     *
     * @return array<string, mixed>
     */
    public function websiteCoursePayload(DirectionExchange $direction, ?string $baseRate = null): array
    {
        $base = $baseRate ?? $this->resolveBaseRate($direction);
        $fixed = $this->calculate($direction, RateMode::Fixed, RateChannel::Website, $base);
        $floating = $this->calculate($direction, RateMode::Floating, RateChannel::Website, $base);
        $display = $floating;

        $fixedNorm = $this->normalizer->toPercentExpressionOrZero($direction->fix_fee ?? '0');
        $floatNorm = $this->normalizer->toPercentExpressionOrZero($direction->floating_fee ?? '0');

        return [
            // display_rate is the initial public rate (= fixed)
            'rate' => $display->finalRate,
            'base_rate' => (string) $base,
            'default_mode' => self::DEFAULT_MODE->value,
            'fixed_rate' => $fixed->finalRate,
            'floating_rate' => $floating->finalRate,
            'display_rate' => $display->finalRate,
            'fee' => [
                'fixed' => [
                    'value' => $fixedNorm['percent'],
                    'unit' => RateAdjustmentUnit::Percent->value,
                ],
                'floating' => [
                    'value' => $floatNorm['percent'],
                    'unit' => RateAdjustmentUnit::Percent->value,
                ],
            ],
            // Legacy map: keep numeric strings for labels; unit is always percent.
            'type_rate' => [
                'fixed' => $fixedNorm['percent'],
                'floating' => $floatNorm['percent'],
                'unit' => RateAdjustmentUnit::Percent->value,
            ],
            'source_timestamp' => $display->sourceTimestamp,
        ];
    }

    /**
     * Force fee strings used by CalculatorMathService to percent expressions.
     *
     * @return array{fixed: string, floating: string}
     */
    public function percentExpressionsForCalculator(DirectionExchange $direction): array
    {
        $fixed = $this->normalizer->toPercentExpressionOrZero($direction->fix_fee ?? '0');
        $floating = $this->normalizer->toPercentExpressionOrZero($direction->floating_fee ?? '0');

        return [
            'fixed' => $fixed['expression'],
            'floating' => $floating['expression'],
        ];
    }

    private function resolveBaseRate(DirectionExchange $direction): string
    {
        // ZELLEUSD outgoing: base is exact USDTTRC20→destination benchmark (never BC ZELLE offers).
        try {
            $zelleResolver = ZelleUsdBenchmarkResolver::make();
            if ($zelleResolver->isZelleOutgoing($direction)) {
                $bench = $zelleResolver->resolve($direction);
                if ($bench->eligible && $bench->benchmarkRate !== null
                    && is_numeric($bench->benchmarkRate)
                    && bccomp($bench->benchmarkRate, '0', self::ROUNDING_SCALE) > 0
                ) {
                    return $bench->benchmarkRate;
                }

                return '0';
            }
        } catch (Throwable $e) {
            Log::error('zelle_benchmark_base_resolve_failed', [
                'direction_id' => $direction->id ?? null,
                'message' => $e->getMessage(),
            ]);
            if ((int) ($direction->id_currency1 ?? 0) === ZelleUsdBenchmarkResolver::ZELLE_CURRENCY_ID) {
                return '0';
            }
        }

        // Derived-market ownership: course_value IS the automatic BASE.
        // Do not route through Calculator profit/add_course (avoids double commercial %).
        $parser = (string) ($direction->parser_source_name ?? '');
        $dirId = (int) ($direction->id ?? 0);
        if (
            $dirId > 0
            && (
                $parser === 'DERIVED_MARKET_BASELINE'
                || DerivedMarketBaselineAuthority::fromStorageApp()->owns($dirId)
            )
        ) {
            $derivedBase = (string) ($direction->course_value ?? '0');
            if (is_numeric($derivedBase) && bccomp($derivedBase, '0', self::ROUNDING_SCALE) > 0) {
                return $derivedBase;
            }

            return '0';
        }

        try {
            $payload = CalculatorFacade::setDirectionExchange($direction)->calculate()->toArray();
            $rate = (string) ($payload['rate'] ?? '0');
            if (is_numeric($rate) && bccomp($rate, '0', self::ROUNDING_SCALE) > 0) {
                return $rate;
            }
        } catch (Throwable $e) {
            Log::error('canonical_rate_base_resolve_failed', [
                'direction_id' => $direction->id ?? null,
                'message' => $e->getMessage(),
            ]);
        }

        $fallback = (string) ($direction->course_value ?? '0');

        return is_numeric($fallback) ? $fallback : '0';
    }

    private function sourceTimestamp(DirectionExchange $direction): ?string
    {
        $updated = $direction->updated_at ?? null;
        if ($updated === null) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($updated)->utc()->toIso8601String();
        } catch (Throwable) {
            return is_string($updated) ? $updated : null;
        }
    }
}
