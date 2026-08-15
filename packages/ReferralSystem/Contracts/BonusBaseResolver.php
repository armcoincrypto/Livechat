<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Contracts;

use iEXPackages\ReferralSystem\DTO\ReferralContext;
use iEXPackages\ReferralSystem\DTO\ReferralMoney;

interface BonusBaseResolver
{
    public function resolveBase(ReferralContext $context): ReferralMoney;
}
