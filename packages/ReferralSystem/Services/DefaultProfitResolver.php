<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\Services;

use iEXPackages\ReferralSystem\Contracts\ProfitResolver;
use iEXPackages\ReferralSystem\DTO\ReferralContext;
use iEXPackages\ReferralSystem\Support\ReferralMath;

class DefaultProfitResolver implements ProfitResolver
{
    public function resolve(ReferralContext $context): array
    {
        $dir = $context->direction;

        $percentage = (string) ($dir->profit_partner ?? '0');
        $fixed      = (string) ($dir->profit_partner_s ?? '0');

        $task = $context->task;
        if ($task && (int) ($task->task_info->direction_city_id ?? 0) > 0 && isset($task->task_info->directionCity)) {
            $city = $task->task_info->directionCity;

            if (isset($city->profit_partner) && (float) $city->profit_partner > 0) {
                $percentage = (string) $city->profit_partner;
            }
            if (isset($city->profit_partner_s) && (float) $city->profit_partner_s > 0) {
                $fixed = (string) $city->profit_partner_s;
            }
        }

        return [
            'percentage' => ReferralMath::norm($percentage, ReferralMath::PERCENT_SCALE),
            'fixed'      => ReferralMath::norm($fixed),
        ];
    }
}
