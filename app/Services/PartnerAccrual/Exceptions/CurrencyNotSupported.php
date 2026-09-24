<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual\Exceptions;

final class CurrencyNotSupported extends PartnerAccrualException
{
    public static function forCode(string $code): self
    {
        return new self('Partner accrual v1 rejects currency '.$code.'; only USD is certified.');
    }

    public function codeName(): string
    {
        return 'CURRENCY_NOT_SUPPORTED';
    }
}
