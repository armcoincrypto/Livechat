<?php

declare(strict_types=1);

namespace iEXPackages\ReferralSystem\DTO;

class CreditResult
{
    public function __construct(
        public bool $credited,
        public string $message,
        public ?int $creditId = null,
        public ?ReferralMoney $amount = null,
    ) {}
}
