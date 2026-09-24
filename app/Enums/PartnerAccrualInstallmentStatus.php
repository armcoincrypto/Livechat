<?php

declare(strict_types=1);

namespace App\Enums;

enum PartnerAccrualInstallmentStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case CREDITED = 'CREDITED';
    case SKIPPED = 'SKIPPED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';

    public function isTerminalCredit(): bool
    {
        return $this === self::CREDITED;
    }

    public function canCredit(): bool
    {
        return $this === self::PENDING || $this === self::FAILED;
    }
}
