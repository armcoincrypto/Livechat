<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual\Exceptions;

final class PlanNotActive extends PartnerAccrualException
{
    public static function forStatus(string $status): self
    {
        return new self('Partner accrual plan is not ACTIVE (status='.$status.').');
    }

    public function codeName(): string
    {
        return 'PLAN_NOT_ACTIVE';
    }
}
