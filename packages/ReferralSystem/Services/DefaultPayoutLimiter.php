<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Services;

use iEXPackages\ReferralSystem\Contracts\PayoutLimiter;
use iEXPackages\ReferralSystem\DTO\ReferralContext;
use iEXPackages\ReferralSystem\Support\ReferralMath;

class DefaultPayoutLimiter implements PayoutLimiter
{
    public function apply(ReferralContext $context, string $amount): string
    {
        $amount = ReferralMath::norm($amount);

        $max = ReferralMath::norm((string) ($context->direction->maximum_payout ?? '0'));
        $min = ReferralMath::norm((string) ($context->direction->minimum_payout ?? '0'));

        if (!ReferralMath::isZero($max) && ReferralMath::cmp($amount, $max) === 1) {
            return $max;
        }

        if (!ReferralMath::isZero($min) && ReferralMath::cmp($amount, $min) === -1) {
            return '0';
        }

        return $amount;
    }
}
