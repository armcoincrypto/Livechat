<?php

namespace iEXPackages\Transaction\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class OrderRateDiagnosticsService
{
    /**
     * @param array{
     *   display_in:string,
     *   display_out:string,
     *   rate_amount:string,
     *   user_discount:string,
     *   promo_bonus:string,
     *   precision:int
     * } $ctx
     *
     * @return array{percent_diff:float|int,new_out_amount:string}
     */
    public function calculate(array $ctx): array
    {
        $precision = (int) ($ctx['precision'] ?? 8);

        $displayIn = BigDecimal::of((string) ($ctx['display_in'] ?? '0'));
        $displayOut = BigDecimal::of((string) ($ctx['display_out'] ?? '0'));
        $rateAmount = BigDecimal::of((string) ($ctx['rate_amount'] ?? '0'));
        $userDiscount = BigDecimal::of((string) ($ctx['user_discount'] ?? '0'));
        $promoBonus = BigDecimal::of((string) ($ctx['promo_bonus'] ?? '0'));

        // mathA = rate_amount * display_in + user_discount + promo_bonus
        $mathA = $rateAmount->multipliedBy($displayIn)->plus($userDiscount)->plus($promoBonus);

        if (!$mathA->isGreaterThan(0) || !$displayOut->isGreaterThan(0)) {
            return [
                'percent_diff' => 0,
                'new_out_amount' => $this->nf(0, $precision),
            ];
        }

        // percentDiff = (display_out / mathA - 1) * 100
        $ratio = $displayOut->dividedBy($mathA, 18, RoundingMode::HALF_UP);
        $percentDiff = $ratio->minus(1)->multipliedBy(100);

        // newOutAmount = max(0, display_out - rate_amount*display_in - user_discount - promo_bonus)
        $newOut = $displayOut
            ->minus($rateAmount->multipliedBy($displayIn))
            ->minus($userDiscount)
            ->minus($promoBonus);

        if ($newOut->isLessThan(0)) {
            $newOut = BigDecimal::zero();
        }

        return [
            'percent_diff' => (float) $this->nf($percentDiff->toFloat(), 2),
            'new_out_amount' => $this->nf($newOut->toFloat(), $precision),
        ];
    }

    private function nf(float|int $value, int $precision): string
    {
        // у тебя уже есть iex_number_format — используй его, если он глобальный:
        return iex_number_format($value, $precision);
    }
}
