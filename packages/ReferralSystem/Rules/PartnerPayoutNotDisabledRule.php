<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Rules;

use iEXPackages\ReferralSystem\Contracts\EligibilityRule;
use iEXPackages\ReferralSystem\DTO\ReferralContext;

class PartnerPayoutNotDisabledRule implements EligibilityRule
{
    public function check(ReferralContext $context): ?string
    {
        // Семантика как у тебя в старом checker:
        // is_pay_referral = 1 => выплаты отключены
        if ((int) $context->partner->is_pay_referral === 1) {
            return 'Администратор отключил партнёру выплату бонусов';
        }

        return null;
    }
}
