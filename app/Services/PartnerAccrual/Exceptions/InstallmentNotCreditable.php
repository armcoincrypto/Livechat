<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual\Exceptions;

final class InstallmentNotCreditable extends PartnerAccrualException
{
    public static function forStatus(int $id, string $status): self
    {
        return new self('Installment '.$id.' status '.$status.' is not creditable.');
    }

    public function codeName(): string
    {
        return 'INSTALLMENT_NOT_CREDITABLE';
    }
}
