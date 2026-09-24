<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual;

use App\Services\PartnerAccrual\Exceptions\CurrencyNotSupported;
use App\Services\PartnerAccrual\Exceptions\GeneratorConstraintViolation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Pure schedule generator: one installment per eligible calendar day,
 * exact sum in USD minor units (cents). No balance mutation.
 */
final class PartnerAccrualPlanGenerator
{
    public function __construct(
        private readonly PartnerAccrualBusinessClock $clock = new PartnerAccrualBusinessClock(),
    ) {}

    /**
     * @param  array{
     *   total_amount: string,
     *   start_date: string,
     *   end_date: string,
     *   currency_code?: string,
     *   schedule_seed?: string|null,
     *   min_installment?: string|null,
     *   max_installment?: string|null,
     *   variance_percent?: int|null
     * }  $input
     * @return array{
     *   currency_code: string,
     *   schedule_seed: string,
     *   installment_count: int,
     *   total_amount: string,
     *   sum: string,
     *   min: string,
     *   max: string,
     *   average: string,
     *   variance_percent: int,
     *   min_installment: string,
     *   max_installment: string,
     *   schedule: list<array{sequence:int,scheduled_for:string,amount:string}>
     * }
     */
    public function generate(array $input): array
    {
        $currency = strtoupper(trim((string) ($input['currency_code'] ?? PartnerAccrualCurrency::V1_CODE)));
        PartnerAccrualCurrency::assertSupported($currency);

        $total = AccrualMoney::of($input['total_amount']);
        if (AccrualMoney::cmp($total, AccrualMoney::zero()) <= 0) {
            throw GeneratorConstraintViolation::message('total_amount must be positive');
        }

        $dates = $this->clock->dateRangeInclusive(
            (string) $input['start_date'],
            (string) $input['end_date']
        );
        $days = count($dates);
        if ($days < 1) {
            throw GeneratorConstraintViolation::message('end_date must be >= start_date');
        }

        $scale = (int) config('partner_accrual.minor_unit_scale', 2);
        $totalMinor = $this->toMinorUnits($total, $scale);
        if ($totalMinor < $days) {
            // Cannot place at least 1 minor unit on each day.
            throw GeneratorConstraintViolation::message(
                sprintf('total %s cannot fund %d positive installments at scale %d', AccrualMoney::scale8($total), $days, $scale)
            );
        }

        $avgMinor = intdiv($totalMinor, $days);
        $variance = $input['variance_percent']
            ?? (int) config('partner_accrual.default_variance_percent', 25);
        $variance = max(0, min(90, (int) $variance));

        $defaultMin = max(1, (int) floor($avgMinor * 0.70));
        $defaultMax = max($defaultMin, (int) ceil($avgMinor * 1.30));

        $minMinor = isset($input['min_installment']) && $input['min_installment'] !== null && $input['min_installment'] !== ''
            ? $this->toMinorUnits(AccrualMoney::of($input['min_installment']), $scale)
            : $defaultMin;
        $maxMinor = isset($input['max_installment']) && $input['max_installment'] !== null && $input['max_installment'] !== ''
            ? $this->toMinorUnits(AccrualMoney::of($input['max_installment']), $scale)
            : $defaultMax;

        if ($minMinor < 1) {
            throw GeneratorConstraintViolation::message('min_installment must be at least one minor unit');
        }
        if ($maxMinor < $minMinor) {
            throw GeneratorConstraintViolation::message('max_installment must be >= min_installment');
        }
        if ($days * $minMinor > $totalMinor) {
            throw GeneratorConstraintViolation::message(
                sprintf('%d × min (%s) exceeds total %s', $days, $this->fromMinorUnits($minMinor, $scale), AccrualMoney::display2($total))
            );
        }
        if ($days * $maxMinor < $totalMinor) {
            throw GeneratorConstraintViolation::message(
                sprintf('%d × max (%s) is below total %s', $days, $this->fromMinorUnits($maxMinor, $scale), AccrualMoney::display2($total))
            );
        }

        $seed = (string) ($input['schedule_seed'] ?? '');
        if ($seed === '') {
            $seed = bin2hex(random_bytes(16));
        }

        $rng = new SeededRng($seed);
        $amountsMinor = [];
        $allocated = 0;

        for ($i = 0; $i < $days - 1; $i++) {
            $remainingDays = $days - 1 - $i;
            $noise = ($rng->nextFloat() * 2.0 - 1.0) * ($variance / 100.0);
            $candidate = (int) round($avgMinor * (1.0 + $noise));
            $candidate = max($minMinor, min($maxMinor, $candidate));

            $remainingBudget = $totalMinor - $allocated;
            $minRemain = $remainingDays * $minMinor;
            $maxRemain = $remainingDays * $maxMinor;

            $low = max($minMinor, $remainingBudget - $maxRemain);
            $high = min($maxMinor, $remainingBudget - $minRemain);
            if ($low > $high) {
                throw GeneratorConstraintViolation::message('unable to satisfy min/max while preserving exact sum');
            }
            $candidate = max($low, min($high, $candidate));

            $amountsMinor[] = $candidate;
            $allocated += $candidate;
        }

        $last = $totalMinor - $allocated;
        if ($last < $minMinor || $last > $maxMinor) {
            throw GeneratorConstraintViolation::message(
                sprintf('final installment %s outside [%s, %s]', $this->fromMinorUnits($last, $scale), $this->fromMinorUnits($minMinor, $scale), $this->fromMinorUnits($maxMinor, $scale))
            );
        }
        $amountsMinor[] = $last;

        $sumCheck = array_sum($amountsMinor);
        if ($sumCheck !== $totalMinor) {
            throw GeneratorConstraintViolation::message('internal exact-sum invariant failed');
        }

        $schedule = [];
        $minSeen = PHP_INT_MAX;
        $maxSeen = 0;
        foreach ($amountsMinor as $idx => $minor) {
            $minSeen = min($minSeen, $minor);
            $maxSeen = max($maxSeen, $minor);
            $schedule[] = [
                'sequence' => $idx + 1,
                'scheduled_for' => $dates[$idx],
                'amount' => AccrualMoney::scale8(AccrualMoney::of($this->fromMinorUnits($minor, $scale))),
            ];
        }

        $avgDisplay = AccrualMoney::display2(
            AccrualMoney::of($this->fromMinorUnits($totalMinor, $scale))->dividedBy($days, 8, RoundingMode::HALF_UP)
        );

        return [
            'currency_code' => $currency,
            'schedule_seed' => $seed,
            'installment_count' => $days,
            'total_amount' => AccrualMoney::scale8($total),
            'sum' => AccrualMoney::scale8(AccrualMoney::of($this->fromMinorUnits($sumCheck, $scale))),
            'min' => AccrualMoney::scale8(AccrualMoney::of($this->fromMinorUnits($minSeen, $scale))),
            'max' => AccrualMoney::scale8(AccrualMoney::of($this->fromMinorUnits($maxSeen, $scale))),
            'average' => AccrualMoney::scale8(AccrualMoney::of($avgDisplay)),
            'variance_percent' => $variance,
            'min_installment' => AccrualMoney::scale8(AccrualMoney::of($this->fromMinorUnits($minMinor, $scale))),
            'max_installment' => AccrualMoney::scale8(AccrualMoney::of($this->fromMinorUnits($maxMinor, $scale))),
            'schedule' => $schedule,
        ];
    }

    private function toMinorUnits(BigDecimal $amount, int $scale): int
    {
        $scaled = $amount->toScale($scale, RoundingMode::HALF_UP);
        $factor = BigDecimal::of(10)->power($scale);
        $minor = $scaled->multipliedBy($factor)->toScale(0, RoundingMode::UNNECESSARY);

        return (int) (string) $minor;
    }

    private function fromMinorUnits(int $minor, int $scale): string
    {
        $factor = BigDecimal::of(10)->power($scale);

        return (string) BigDecimal::of($minor)->dividedBy($factor, $scale, RoundingMode::UNNECESSARY);
    }
}
