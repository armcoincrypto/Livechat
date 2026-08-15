<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\DTO;

class ReferralMoney
{
    public function __construct(
        public string $amount,   // decimal-string
        public string $currency, // e.g. USD
        public int $scale = 8
    ) {}

    public static function zero(string $currency, int $scale = 8): self
    {
        return new self('0', $currency, $scale);
    }
}
