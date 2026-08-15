<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Contracts;

use iEXPackages\ReferralSystem\DTO\ReferralContext;
use iEXPackages\ReferralSystem\DTO\ReferralMoney;

interface BonusCalculator
{
    /**
     * @return array{
     *   amount: string,
     *   percent: string,
     *   fixed: string,
     *   method: string
     * }
     */
    public function calculate(ReferralContext $context, ReferralMoney $base): array;
}
