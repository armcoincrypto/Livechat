<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual;

/**
 * Money strings for UI — never float JSON as authoritative.
 */
final class PartnerAccrualMoneyPresenter
{
    public static function storage(string|\Brick\Math\BigDecimal $amount): string
    {
        return AccrualMoney::scale8(AccrualMoney::of($amount));
    }

    public static function display(string|\Brick\Math\BigDecimal $amount): string
    {
        return AccrualMoney::display2(AccrualMoney::of($amount));
    }

    public static function remaining(string $total, string $credited): string
    {
        $rem = AccrualMoney::of($total)->minus(AccrualMoney::of($credited));
        if (AccrualMoney::cmp($rem, AccrualMoney::zero()) < 0) {
            // Never show negative remaining in UI — signal mismatch instead.
            return self::display(AccrualMoney::zero());
        }

        return self::display($rem);
    }

    public static function remainingNegative(string $total, string $credited): bool
    {
        return AccrualMoney::cmp(
            AccrualMoney::of($total)->minus(AccrualMoney::of($credited)),
            AccrualMoney::zero()
        ) < 0;
    }
}
