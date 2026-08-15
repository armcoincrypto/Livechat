<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Rules;

use iEXPackages\ReferralSystem\Contracts\EligibilityRule;
use iEXPackages\ReferralSystem\DTO\ReferralContext;

class DirectionPartnerEnabledRule implements EligibilityRule
{
    public function check(ReferralContext $context): ?string
    {
        if ((int) ($context->direction->is_not_partner ?? 0) === 1) {
            return "По направлению {$context->direction->tech_name} отключены выплаты бонусов";
        }

        return null;
    }
}
