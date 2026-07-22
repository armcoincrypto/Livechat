<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Console\Schedule\ExchangeSchedule;
use PHPUnit\Framework\TestCase;

final class ExchangeScheduleCadenceTest extends TestCase
{
    public function test_one_minute_means_every_minute_not_ten_seconds(): void
    {
        $this->assertSame(
            ['method', 'everyMinute'],
            ExchangeSchedule::resolveMinuteCadence(1),
        );
    }

    public function test_two_minutes_and_default_zero(): void
    {
        $this->assertSame(
            ['method', 'everyTwoMinutes'],
            ExchangeSchedule::resolveMinuteCadence(2),
        );
        $this->assertSame(
            ['method', 'everyMinute'],
            ExchangeSchedule::resolveMinuteCadence(0),
        );
    }

    public function test_arbitrary_minutes_use_cron_expression(): void
    {
        $this->assertSame(
            ['cron', '*/7 * * * *'],
            ExchangeSchedule::resolveMinuteCadence(7),
        );
    }

    public function test_legacy_sub_minute_enum_values_are_not_used(): void
    {
        // Former map sent value 1 to everyTenSeconds and value 8 to everyFiveSeconds.
        $this->assertNotSame('everyTenSeconds', ExchangeSchedule::resolveMinuteCadence(1)[1]);
        $this->assertNotSame('everyFiveSeconds', ExchangeSchedule::resolveMinuteCadence(8)[1]);
        $this->assertSame(['cron', '*/8 * * * *'], ExchangeSchedule::resolveMinuteCadence(8));
    }
}
