<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Rules;

use iEXPackages\ReferralSystem\Contracts\EligibilityRule;
use iEXPackages\ReferralSystem\DTO\ReferralContext;

class EnabledRule implements EligibilityRule
{
    public function check(ReferralContext $context): ?string
    {
        // legacy: 0 — включено, 1 — выключено
        if ((int) iEXSetting('enabled_referral_system') === 0) {
            return 'Реферальная система отключена';
        }

        return null;
    }
}
