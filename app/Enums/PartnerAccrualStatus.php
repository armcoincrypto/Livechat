<?php

declare(strict_types=1);

namespace App\Enums;

enum PartnerAccrualStatus: string
{
    case DRAFT = 'DRAFT';
    case ACTIVE = 'ACTIVE';
    case PAUSED = 'PAUSED';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
    case ERROR = 'ERROR';

    public function isCreditable(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Statuses that block overlapping plan creation for the same user+currency.
     *
     * @return list<self>
     */
    public static function blockingOverlap(): array
    {
        return [self::ACTIVE, self::PAUSED, self::DRAFT];
    }
}
