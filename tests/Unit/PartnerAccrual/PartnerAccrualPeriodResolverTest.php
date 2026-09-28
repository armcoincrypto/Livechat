<?php

declare(strict_types=1);

namespace Tests\Unit\PartnerAccrual;

use App\Services\PartnerAccrual\PartnerAccrualBusinessClock;
use App\Services\PartnerAccrual\PartnerAccrualPeriodResolver;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class PartnerAccrualPeriodResolverTest extends TestCase
{
    private function resolve(string $businessDate): array
    {
        $clock = new PartnerAccrualBusinessClock();
        $resolver = new PartnerAccrualPeriodResolver($clock);

        return $resolver->nextFullCalendarMonth(
            CarbonImmutable::parse($businessDate, $clock->timezone())->startOfDay()
        );
    }

    public function test_september_maps_to_october(): void
    {
        $p = $this->resolve('2026-09-28');
        $this->assertSame('NEXT_FULL_CALENDAR_MONTH', $p['period_mode']);
        $this->assertSame('Europe/Moscow', $p['timezone']);
        $this->assertSame('2026-09-28', $p['business_date']);
        $this->assertSame('2026-10-01', $p['start_date']);
        $this->assertSame('2026-10-31', $p['end_date']);
    }

    public function test_january_to_february_non_leap(): void
    {
        $p = $this->resolve('2026-01-31');
        $this->assertSame('2026-02-01', $p['start_date']);
        $this->assertSame('2026-02-28', $p['end_date']);
    }

    public function test_january_to_february_leap(): void
    {
        $p = $this->resolve('2028-01-15');
        $this->assertSame('2028-02-01', $p['start_date']);
        $this->assertSame('2028-02-29', $p['end_date']);
    }

    public function test_april_to_may(): void
    {
        $p = $this->resolve('2026-04-30');
        $this->assertSame('2026-05-01', $p['start_date']);
        $this->assertSame('2026-05-31', $p['end_date']);
    }

    public function test_december_to_january_next_year(): void
    {
        $p = $this->resolve('2027-12-31');
        $this->assertSame('2028-01-01', $p['start_date']);
        $this->assertSame('2028-01-31', $p['end_date']);
    }

    public function test_month_end_current_date(): void
    {
        $p = $this->resolve('2026-09-30');
        $this->assertSame('2026-10-01', $p['start_date']);
        $this->assertSame('2026-10-31', $p['end_date']);
    }

    public function test_explicit_moscow_timezone_around_utc_midnight(): void
    {
        // 2026-09-28 22:30 UTC == 2026-09-29 01:30 Moscow → next month still October.
        $moscow = CarbonImmutable::parse('2026-09-28 22:30:00', 'UTC')->setTimezone('Europe/Moscow');
        $this->assertSame('2026-09-29', $moscow->toDateString());
        $resolver = new PartnerAccrualPeriodResolver(new PartnerAccrualBusinessClock());
        $p = $resolver->nextFullCalendarMonth($moscow);
        $this->assertSame('Europe/Moscow', $p['timezone']);
        $this->assertSame('2026-09-29', $p['business_date']);
        $this->assertSame('2026-10-01', $p['start_date']);
        $this->assertSame('2026-10-31', $p['end_date']);
    }
}
