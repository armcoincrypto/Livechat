<?php

declare(strict_types=1);

namespace App\Services\Rates;

/**
 * Applies documented commercial coefficients exactly once to a raw baseline.
 *
 * Orientation: baseline and result are customer "out per 1 in" (course_value style).
 * Profit percent reduces customer receive amount: baseline * (1 - profit/100).
 * Optional add_course1 is treated as an additional percent coefficient when non-zero
 * only if $applyAddCourse1 is true (Rapira/TON path historically applied profit only).
 */
final class RateConfiguredExpectation
{
    public const SCALE = 18;

    public function __construct(
        private readonly RateSanityGuard $guard = new RateSanityGuard(),
    ) {
    }

    /**
     * @return array{
     *   baseline: string|null,
     *   orientation_normalization: string,
     *   fiat_conversion: string|null,
     *   direction_profit_percent: string,
     *   payment_system_fee_percent: string,
     *   fixed_commission: string,
     *   percentage_commission: string,
     *   other_coefficient: string|null,
     *   expected_final_rate: string|null,
     *   actual_final_rate: string|null,
     *   raw_market_deviation: float|null,
     *   expected_configured_deviation: float|null,
     *   unexplained_deviation: float|null,
     *   reason: string|null
     * }
     */
    public function analyze(
        ?string $baseline,
        ?string $actual,
        string $profitPercent = '0',
        string $paymentSystemFeePercent = '0',
        string $fixedCommission = '0',
        string $percentageCommission = '0',
        ?string $otherCoefficient = null,
        bool $applyAddCourse1 = false,
        string $addCourse1Percent = '0',
    ): array {
        $base = $baseline !== null ? $this->guard->normalize($baseline) : null;
        $act = $actual !== null ? $this->guard->normalize($actual) : null;

        $breakdown = [
            'baseline' => $base,
            'orientation_normalization' => 'course_value = out_per_1_in',
            'fiat_conversion' => null,
            'direction_profit_percent' => $this->num($profitPercent),
            'payment_system_fee_percent' => $this->num($paymentSystemFeePercent),
            'fixed_commission' => $this->num($fixedCommission),
            'percentage_commission' => $this->num($percentageCommission),
            'other_coefficient' => $otherCoefficient,
            'expected_final_rate' => null,
            'actual_final_rate' => $act,
            'raw_market_deviation' => null,
            'expected_configured_deviation' => null,
            'unexplained_deviation' => null,
            'reason' => null,
        ];

        if ($act === null) {
            $breakdown['reason'] = 'invalid_actual';
            return $breakdown;
        }

        if ($base === null) {
            $breakdown['reason'] = 'no_baseline';
            return $breakdown;
        }

        $expected = $base;
        $profit = $this->num($profitPercent);
        $expected = bcmul($expected, bcsub('1', bcdiv($profit, '100', self::SCALE), self::SCALE), self::SCALE);

        $psFee = $this->num($paymentSystemFeePercent);
        if (bccomp($psFee, '0', self::SCALE) !== 0) {
            $expected = bcmul($expected, bcsub('1', bcdiv($psFee, '100', self::SCALE), self::SCALE), self::SCALE);
        }

        $pctComm = $this->num($percentageCommission);
        if (bccomp($pctComm, '0', self::SCALE) !== 0) {
            $expected = bcmul($expected, bcsub('1', bcdiv($pctComm, '100', self::SCALE), self::SCALE), self::SCALE);
        }

        if ($applyAddCourse1) {
            $add = $this->num($addCourse1Percent);
            if (bccomp($add, '0', self::SCALE) !== 0) {
                // add_course1 historically stored as signed percent points (e.g. -3).
                $expected = bcmul($expected, bcadd('1', bcdiv($add, '100', self::SCALE), self::SCALE), self::SCALE);
            }
        }

        // Fixed commission cannot be applied without a notional amount; record only.
        $breakdown['expected_final_rate'] = $this->guard->normalize($expected);
        $expected = $breakdown['expected_final_rate'];

        $breakdown['raw_market_deviation'] = $this->pctDeviation($act, $base);
        if ($expected !== null) {
            $breakdown['expected_configured_deviation'] = $this->pctDeviation($expected, $base);
            $breakdown['unexplained_deviation'] = $this->pctDeviation($act, $expected);
        }

        return $breakdown;
    }

    public function classifyUnexplained(?float $unexplainedPct): string
    {
        if ($unexplainedPct === null) {
            return 'no_baseline';
        }
        $a = abs($unexplainedPct);
        if ($a <= 1.0) {
            return 'normal';
        }
        if ($a <= 3.0) {
            return 'warning';
        }
        if ($a <= 7.0) {
            return 'high';
        }
        if ($a <= 12.0) {
            return 'critical';
        }

        return 'extreme';
    }

    private function pctDeviation(string $actual, string $reference): ?float
    {
        if (bccomp($reference, '0', self::SCALE) !== 1) {
            return null;
        }
        $ratio = bcdiv($actual, $reference, self::SCALE);
        $pct = bcmul(bcsub($ratio, '1', 8), '100', 6);

        return (float) $pct;
    }

    private function num(string $value): string
    {
        $value = trim(str_replace(',', '.', $value));
        if ($value === '' || !is_numeric($value)) {
            return '0';
        }

        return bcmul($value, '1', self::SCALE);
    }
}
