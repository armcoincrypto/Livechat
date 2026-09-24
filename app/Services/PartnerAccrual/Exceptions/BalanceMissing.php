<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual\Exceptions;

final class BalanceMissing extends PartnerAccrualException
{
    public static function forUser(int $userId): self
    {
        return new self('user_balance row missing for user '.$userId.' and could not be created safely.');
    }

    public function codeName(): string
    {
        return 'BALANCE_MISSING';
    }
}
