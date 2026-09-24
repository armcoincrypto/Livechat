<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual\Exceptions;

use RuntimeException;

abstract class PartnerAccrualException extends RuntimeException
{
    abstract public function codeName(): string;
}
