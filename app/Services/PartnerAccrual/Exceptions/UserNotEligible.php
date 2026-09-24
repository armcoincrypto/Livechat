<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual\Exceptions;

final class UserNotEligible extends PartnerAccrualException
{
    public static function banned(int $userId): self
    {
        return new self('User '.$userId.' is banned; accrual credit refused.');
    }

    public static function deactivated(int $userId): self
    {
        return new self('User '.$userId.' is deactivated; accrual credit refused.');
    }

    public function codeName(): string
    {
        return 'USER_NOT_ELIGIBLE';
    }
}
