<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Services;

use iEXPackages\ReferralSystem\Contracts\CurrencyConverter;
use iEXPackages\ReferralSystem\Support\ReferralMath;

class DefaultCurrencyConverter implements CurrencyConverter
{
    public function __construct(
        private readonly ReferralBonusConverter $bonusConverter,
    ) {}

    public function convert(string $fromCode, string $toCode, string $amount, ?string $internalRate = null): string
    {
        $amount = ReferralMath::norm($amount);
        if (ReferralMath::isZero($amount)) {
            return '0';
        }

        $rate = ($internalRate !== null && !ReferralMath::isZero($internalRate)) ? (float) ReferralMath::norm($internalRate) : null;

        $converted = (float) $this->bonusConverter->convertGiveToBonus(
            fromCode: $fromCode,
            bonusCode: $toCode,
            amount: (float) $amount,
            internalRate: $rate
        );

        return ReferralMath::norm((string) $converted);
    }
}
