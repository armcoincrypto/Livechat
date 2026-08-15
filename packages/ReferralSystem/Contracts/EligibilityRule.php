<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Contracts;

use iEXPackages\ReferralSystem\DTO\ReferralContext;

interface EligibilityRule
{
    /**
     * @return string|null null = ok, иначе текст причины отказа
     */
    public function check(ReferralContext $context): ?string;
}
