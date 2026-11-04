<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Console\Schedule\ExchangeSchedule;
use PHPUnit\Framework\TestCase;

/**
 * W1A: disabled recount schedule gate + conditional authority-drift cadence.
 * Pure helpers only — no Laravel container / production mutation.
 */
final class ExchangeScheduleW1aWorkloadElimTest extends TestCase
{
    public function test_order_recount_schedule_admitted_only_when_cron_mode_nonzero(): void
    {
        $this->assertFalse(ExchangeSchedule::orderRecountScheduleAdmitted(0));
        $this->assertTrue(ExchangeSchedule::orderRecountScheduleAdmitted(1));
        $this->assertTrue(ExchangeSchedule::orderRecountScheduleAdmitted(2));
    }

    public function test_authority_drift_cadence_defaults_to_fifteen_when_alerts_off(): void
    {
        $this->assertSame(15, ExchangeSchedule::authorityDriftCadenceMinutes(false, false));
        $this->assertSame(5, ExchangeSchedule::authorityDriftCadenceMinutes(true, false));
        $this->assertSame(5, ExchangeSchedule::authorityDriftCadenceMinutes(false, true));
        $this->assertSame(5, ExchangeSchedule::authorityDriftCadenceMinutes(true, true));
    }

    public function test_authority_drift_due_minutes_for_fifteen_and_five(): void
    {
        $this->assertTrue(ExchangeSchedule::authorityDriftDueAtMinute(0, 15));
        $this->assertFalse(ExchangeSchedule::authorityDriftDueAtMinute(5, 15));
        $this->assertTrue(ExchangeSchedule::authorityDriftDueAtMinute(15, 15));
        $this->assertTrue(ExchangeSchedule::authorityDriftDueAtMinute(30, 15));
        $this->assertTrue(ExchangeSchedule::authorityDriftDueAtMinute(45, 15));
        $this->assertFalse(ExchangeSchedule::authorityDriftDueAtMinute(10, 15));

        $this->assertTrue(ExchangeSchedule::authorityDriftDueAtMinute(0, 5));
        $this->assertTrue(ExchangeSchedule::authorityDriftDueAtMinute(5, 5));
        $this->assertFalse(ExchangeSchedule::authorityDriftDueAtMinute(7, 5));
    }

    public function test_schedule_source_wires_w1a_gates(): void
    {
        $path = dirname(__DIR__, 2).'/app/Console/Schedule/ExchangeSchedule.php';
        $src = (string) file_get_contents($path);

        $this->assertStringContainsString('orderRecountScheduleAdmitted()', $src);
        $this->assertStringContainsString('authorityDriftDueAtMinute', $src);
        $this->assertStringContainsString('EXSWAPING_AUTHORITY_DRIFT_EVERY_FIVE', $src);
        $this->assertStringContainsString('rates:authority-drift-monitor --format=summary', $src);

        // Must not retain unconditional everyFiveMinutes on authority-drift.
        $pos = strpos($src, 'rates:authority-drift-monitor --format=summary');
        $this->assertNotFalse($pos);
        $window = substr($src, $pos, 700);
        $this->assertStringNotContainsString(
            '->everyFiveMinutes()',
            $window,
            'authority-drift must use conditional due-minute gate, not everyFiveMinutes()'
        );
        $this->assertStringContainsString('->everyMinute()', $window);
        $this->assertStringContainsString('whenAuthorityDriftDue', $window);
    }
}
