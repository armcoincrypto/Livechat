<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual;

use Carbon\CarbonImmutable;

/**
 * Authoritative accrual period for admin amount-only plan creation.
 *
 * PERIOD_MODE=NEXT_FULL_CALENDAR_MONTH in Europe/Moscow business TZ.
 * Never derive this only in the browser — Preview/Create must call this.
 */
final class PartnerAccrualPeriodResolver
{
    public const MODE_NEXT_FULL_CALENDAR_MONTH = 'NEXT_FULL_CALENDAR_MONTH';

    public function __construct(
        private readonly PartnerAccrualBusinessClock $clock = new PartnerAccrualBusinessClock(),
    ) {}

    /**
     * @return array{
     *   period_mode: string,
     *   timezone: string,
     *   business_date: string,
     *   start_date: string,
     *   end_date: string
     * }
     */
    public function nextFullCalendarMonth(?CarbonImmutable $businessDate = null): array
    {
        $tz = $this->clock->timezone();
        $today = $businessDate
            ? $businessDate->setTimezone($tz)->startOfDay()
            : $this->clock->today();

        $start = $today->startOfMonth()->addMonth()->startOfDay();
        $end = $start->endOfMonth()->startOfDay();

        return [
            'period_mode' => self::MODE_NEXT_FULL_CALENDAR_MONTH,
            'timezone' => $tz,
            'business_date' => $today->toDateString(),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ];
    }
}
