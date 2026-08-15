<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Rules;

use iEXPackages\ReferralSystem\Contracts\EligibilityRule;
use iEXPackages\ReferralSystem\DTO\ReferralContext;

class PartnerEmailVerifiedRule implements EligibilityRule
{
    public function check(ReferralContext $context): ?string
    {
        if ((int) iEXSetting('is_verified_referral') !== 1) {
            return null;
        }

        return $context->partner->email_verified_at !== null
            ? null
            : 'Партнёр не подтвердил e-mail адрес';
    }
}
