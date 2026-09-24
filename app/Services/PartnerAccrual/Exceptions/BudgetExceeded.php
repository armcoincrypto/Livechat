<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual\Exceptions;

final class BudgetExceeded extends PartnerAccrualException
{
    public static function forAmounts(string $installment, string $remaining): self
    {
        return new self(
            'Installment amount '.$installment.' exceeds remaining plan budget '.$remaining.'.'
        );
    }

    public function codeName(): string
    {
        return 'BUDGET_EXCEEDED';
    }
}
