<?php

declare(strict_types=1);

namespace App\Services\PartnerAccrual;

use Carbon\CarbonImmutable;

/**
 * Business-calendar clock for partner accrual (Europe/Moscow by default).
 * Never mix UTC date boundaries with business dates accidentally.
 */
final class PartnerAccrualBusinessClock
{
    public function timezone(): string
    {
        return (string) config('partner_accrual.business_timezone', config('app.timezone', 'Europe/Moscow'));
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    public function today(): CarbonImmutable
    {
        return $this->now()->startOfDay();
    }

    public function todayDateString(): string
    {
        return $this->today()->toDateString();
    }

    public function parseBusinessDate(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, $this->timezone())->startOfDay();
    }

    /**
     * Inclusive calendar days between start and end (business TZ).
     */
    public function inclusiveDayCount(string $startDate, string $endDate): int
    {
        $start = $this->parseBusinessDate($startDate);
        $end = $this->parseBusinessDate($endDate);
        if ($end->lt($start)) {
            return 0;
        }

        return (int) $start->diffInDays($end) + 1;
    }

    /**
     * @return list<string> Y-m-d dates inclusive
     */
    public function dateRangeInclusive(string $startDate, string $endDate): array
    {
        $start = $this->parseBusinessDate($startDate);
        $end = $this->parseBusinessDate($endDate);
        if ($end->lt($start)) {
            return [];
        }

        $dates = [];
        for ($d = $start; $d->lte($end); $d = $d->addDay()) {
            $dates[] = $d->toDateString();
        }

        return $dates;
    }
}
