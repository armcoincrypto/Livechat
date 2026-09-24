<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual\Exceptions;

final class AccountingInvariantViolation extends PartnerAccrualException
{
    public static function message(string $detail): self
    {
        return new self('Accounting invariant violation: '.$detail);
    }

    public function codeName(): string
    {
        return 'ACCOUNTING_INVARIANT_VIOLATION';
    }
}
