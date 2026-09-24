<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Safe decimal math for partner accrual (no PHP float accounting).
 * Legacy user_balance.balance remains float/double — convert at the boundary only.
 */
final class AccrualMoney
{
    public static function of(string|int|float|BigDecimal $value): BigDecimal
    {
        if ($value instanceof BigDecimal) {
            return $value;
        }

        // Never accept float into plan accounting without string casting first from
        // a trusted decimal string. Float inputs are stringified for legacy boundary only.
        if (is_float($value)) {
            return BigDecimal::of(sprintf('%.8F', $value));
        }

        return BigDecimal::of((string) $value);
    }

    public static function scale8(BigDecimal $value): string
    {
        return (string) $value->toScale(PartnerAccrualCurrency::STORAGE_SCALE, RoundingMode::HALF_UP);
    }

    public static function display2(BigDecimal $value): string
    {
        return (string) $value->toScale(PartnerAccrualCurrency::USD_DISPLAY_PRECISION, RoundingMode::HALF_UP);
    }

    public static function zero(): BigDecimal
    {
        return BigDecimal::zero()->toScale(PartnerAccrualCurrency::STORAGE_SCALE);
    }

    /**
     * Convert accrual decimal to legacy float column write value.
     * Documented debt: production user_balance.balance is float/double.
     */
    public static function toLegacyBalanceScalar(BigDecimal $value): float
    {
        return (float) self::scale8($value);
    }

    public static function fromLegacyBalance(mixed $balance): BigDecimal
    {
        if ($balance === null || $balance === '') {
            return self::zero();
        }

        if (is_float($balance) || is_int($balance)) {
            return BigDecimal::of(sprintf('%.8F', (float) $balance));
        }

        return BigDecimal::of((string) $balance);
    }

    public static function cmp(BigDecimal $a, BigDecimal $b): int
    {
        return $a->compareTo($b);
    }

    public static function eq(BigDecimal $a, BigDecimal $b): bool
    {
        return $a->isEqualTo($b);
    }
}
