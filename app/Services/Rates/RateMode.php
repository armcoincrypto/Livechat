<?php

declare(strict_types=1);

namespace App\Services\Rates;

enum RateMode: string
{
    case Fixed = 'fixed';
    case Floating = 'floating';

    public static function fromTypeRateOption(int|string|null $typeRate): self
    {
        // Legacy: 0 = fixed, 1 = floating
        return ((int) $typeRate) === 1 ? self::Floating : self::Fixed;
    }

    public function toTypeRateOption(): int
    {
        return $this === self::Floating ? 1 : 0;
    }
}
