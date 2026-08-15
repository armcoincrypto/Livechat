<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Contracts;

use iEXPackages\ReferralSystem\DTO\ReferralContext;

interface ProfitResolver
{
    /** @return array{percentage: string, fixed: string} */
    public function resolve(ReferralContext $context): array;
}
