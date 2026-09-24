<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual\Exceptions;

final class InstallmentAlreadyCredited extends PartnerAccrualException
{
    public static function forId(int $installmentId): self
    {
        return new self('Installment '.$installmentId.' already CREDITED (idempotent).');
    }

    public function codeName(): string
    {
        return 'ALREADY_CREDITED';
    }
}
