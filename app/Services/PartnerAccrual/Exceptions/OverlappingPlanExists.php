<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual\Exceptions;

final class OverlappingPlanExists extends PartnerAccrualException
{
    public static function forUser(int $userId, string $currency): self
    {
        return new self(
            'Overlapping ACTIVE/PAUSED/DRAFT partner accrual plan exists for user '.$userId.' currency '.$currency.'.'
        );
    }

    public function codeName(): string
    {
        return 'OVERLAPPING_PLAN';
    }
}
