<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual\Exceptions;

final class InvalidScheduleSeed extends PartnerAccrualException
{
    public static function message(string $detail): self
    {
        return new self('Invalid partner accrual schedule_seed: '.$detail);
    }

    public function codeName(): string
    {
        return 'INVALID_SCHEDULE_SEED';
    }
}
