<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Contracts;

use iEXPackages\ReferralSystem\DTO\ReferralContext;

interface PayoutLimiter
{
    public function apply(ReferralContext $context, string $amount): string;
}
