<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class ReferralMath
{
    /**
     * 🔥 Единственное место, где меняются знаки.
     * Хочешь больше/меньше — меняешь ТОЛЬКО тут.
     */
    public const SCALE = 2;

    /**
     * Для процентов (0.0000).
     */
    public const PERCENT_SCALE = 4;

    /**
     * Режим округления (Brick). Для BCMath — через SCALE.
     */
    private const ROUNDING = RoundingMode::HALF_UP;

    public static function hasBrick(): bool
    {
        return class_exists(BigDecimal::class);
    }

    public static function norm(string|int|float|null $v, ?int $scale = null): string
    {
        $scale ??= self::SCALE;
        $raw = self::sanitize($v);

        if (self::hasBrick()) {
            return BigDecimal::of($raw)->toScale($scale, self::ROUNDING)->__toString();
        }

        return bcadd($raw, '0', $scale);
    }

    public static function add(string|int|float $a, string|int|float $b, ?int $scale = null): string
    {
        $scale ??= self::SCALE;

        if (self::hasBrick()) {
            return BigDecimal::of(self::sanitize($a))
                ->plus(BigDecimal::of(self::sanitize($b)))
                ->toScale($scale, self::ROUNDING)
                ->__toString();
        }

        return bcadd(self::sanitize($a), self::sanitize($b), $scale);
    }

    public static function sub(string|int|float $a, string|int|float $b, ?int $scale = null): string
    {
        $scale ??= self::SCALE;

        if (self::hasBrick()) {
            return BigDecimal::of(self::sanitize($a))
                ->minus(BigDecimal::of(self::sanitize($b)))
                ->toScale($scale, self::ROUNDING)
                ->__toString();
        }

        return bcsub(self::sanitize($a), self::sanitize($b), $scale);
    }

    public static function mul(string|int|float $a, string|int|float $b, ?int $scale = null): string
    {
        $scale ??= self::SCALE;

        if (self::hasBrick()) {
            return BigDecimal::of(self::sanitize($a))
                ->multipliedBy(BigDecimal::of(self::sanitize($b)))
                ->toScale($scale, self::ROUNDING)
                ->__toString();
        }

        return bcmul(self::sanitize($a), self::sanitize($b), $scale);
    }

    public static function div(string|int|float $a, string|int|float $b, ?int $scale = null): string
    {
        $scale ??= self::SCALE;

        $bb = self::sanitize($b);
        if (self::cmp($bb, '0', $scale) === 0) {
            return '0';
        }

        if (self::hasBrick()) {
            return BigDecimal::of(self::sanitize($a))
                ->dividedBy(BigDecimal::of($bb), $scale, self::ROUNDING)
                ->__toString();
        }

        return bcdiv(self::sanitize($a), $bb, $scale);
    }

    /**
     * -1, 0, 1
     */
    public static function cmp(string|int|float $a, string|int|float $b, ?int $scale = null): int
    {
        $scale ??= self::SCALE;

        if (self::hasBrick()) {
            return BigDecimal::of(self::sanitize($a))
                ->compareTo(BigDecimal::of(self::sanitize($b)));
        }

        return bccomp(self::sanitize($a), self::sanitize($b), $scale);
    }

    public static function isZero(string|int|float|null $v, ?int $scale = null): bool
    {
        return self::cmp(self::sanitize($v), '0', $scale) === 0;
    }

    public static function display(string $v): string
    {
        $v = trim($v);
        if ($v === '' || $v === '0') return '0';
        if (str_contains($v, '.')) {
            $v = rtrim($v, '0');
            $v = rtrim($v, '.');
        }
        return $v === '' ? '0' : $v;
    }

    private static function sanitize(string|int|float|null $v): string
    {
        if ($v === null || $v === '') return '0';
        $s = (string) $v;
        $s = str_replace([' ', ','], ['', '.'], trim($s));
        if ($s === '' || !is_numeric($s)) return '0';
        return $s;
    }
}
