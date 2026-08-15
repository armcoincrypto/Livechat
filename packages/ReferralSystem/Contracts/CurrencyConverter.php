<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Contracts;

interface CurrencyConverter
{
    /**
     * @param string $amount decimal-string
     * @param string|null $internalRate decimal-string|null
     */
    public function convert(string $fromCode, string $toCode, string $amount, ?string $internalRate = null): string;
}
