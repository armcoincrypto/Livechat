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
 * Formula: final = base × (1 + fee_percent / 100)
 * Default public / order mode: FLOATING.
 * BestChange XML: always FLOATING (never fix_fee).
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

        $rawFee = $mode === RateMode::Fixed
            ? ($direction->fix_fee ?? '0')
            : ($direction->floating_fee ?? '0');

        // When type_rate is disabled, still expose fixed/floating as base (0% fee)
        // for API symmetry. BestChange always uses floating_fee (see calculateForExport).
        if ((int) ($direction->is_type_rate ?? 0) !== 1
            && $channel !== RateChannel::Bestchange
        ) {
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
