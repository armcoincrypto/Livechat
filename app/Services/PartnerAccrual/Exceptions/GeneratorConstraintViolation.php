<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual\Exceptions;

final class GeneratorConstraintViolation extends PartnerAccrualException
{
    public static function message(string $detail): self
    {
        return new self('Partner accrual schedule generation failed: '.$detail);
    }

    public function codeName(): string
    {
        return 'GENERATOR_CONSTRAINT_VIOLATION';
    }
}
