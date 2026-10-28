<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Console\Schedule\BestchangeAdmissionPolicy;
use App\Console\Schedule\ExchangeSchedule;
use PHPUnit\Framework\TestCase;

final class BestchangeAdmissionPolicyTest extends TestCase
{
    public function test_offset_six_clears_courses_jitter_and_avoids_competitors_five(): void
    {
        $this->assertSame(6, BestchangeAdmissionPolicy::ADMISSION_OFFSET_MINUTES);
        $this->assertSame(6, ExchangeSchedule::BESTCHANGE_ADMISSION_OFFSET_MINUTES);
        $this->assertSame(
            '6,36 * * * *',
            ExchangeSchedule::cronExpressionForMinutesWithOffset(30, 6)
        );
        // Competitors owns :05 (3-57/2); nominal BC must not land there.
        $this->assertFalse(BestchangeAdmissionPolicy::isNominalAdmissionMinute(5, 30, 6));
        $this->assertTrue(BestchangeAdmissionPolicy::isNominalAdmissionMinute(6, 30, 6));
        $this->assertTrue(BestchangeAdmissionPolicy::isNominalAdmissionMinute(36, 30, 6));
        $this->assertFalse(BestchangeAdmissionPolicy::isNominalAdmissionMinute(2, 30, 6));
        $this->assertFalse(BestchangeAdmissionPolicy::isNominalAdmissionMinute(0, 30, 6));
        $this->assertFalse(BestchangeAdmissionPolicy::isNominalAdmissionMinute(32, 30, 6));
    }

    public function test_wave1_offset_two_reproduced_as_insufficient_geometry(): void
    {
        // Document the failure mode: +2 sits inside courses occupancy.
        $this->assertTrue(BestchangeAdmissionPolicy::isNominalAdmissionMinute(2, 30, 2));
        $this->assertNotSame(
            2,
            BestchangeAdmissionPolicy::ADMISSION_OFFSET_MINUTES
        );
    }

    public function test_recovery_catchup_only_after_missed_nominal_cycle(): void
    {
        // Recovery threshold must exceed the 30m rates cadence so catch-up is
        // not the steady-state admission path (MODEL B: 45m).
        $this->assertSame(2700, BestchangeAdmissionPolicy::OVERDUE_CATCHUP_SECONDS);
        $this->assertGreaterThan(1800, BestchangeAdmissionPolicy::OVERDUE_CATCHUP_SECONDS);

        // Fresh / mid-cycle: only nominal minutes (no 15m steady-state catch-up).
        $this->assertFalse(BestchangeAdmissionPolicy::shouldAdmit(4, 100, 30, 6, 2700));
        $this->assertTrue(BestchangeAdmissionPolicy::shouldAdmit(6, 100, 30, 6, 2700));
        $this->assertFalse(BestchangeAdmissionPolicy::shouldAdmit(17, 900, 30, 6, 2700));
        $this->assertFalse(BestchangeAdmissionPolicy::shouldAdmit(17, 1800, 30, 6, 2700));
        $this->assertFalse(BestchangeAdmissionPolicy::shouldAdmit(17, 2699, 30, 6, 2700));

        // Recovery: any minute after missed nominal (+45m).
        $this->assertTrue(BestchangeAdmissionPolicy::shouldAdmit(4, 2700, 30, 6, 2700));
        $this->assertTrue(BestchangeAdmissionPolicy::shouldAdmit(17, 2701, 30, 6, 2700));
        $this->assertTrue(BestchangeAdmissionPolicy::shouldAdmit(0, 3600, 30, 6, 2700));
    }

    public function test_non_bc_defers_only_when_recovery_overdue(): void
    {
        $this->assertFalse(BestchangeAdmissionPolicy::shouldDeferNonBc(null));
        $this->assertFalse(BestchangeAdmissionPolicy::shouldDeferNonBc(899));
        $this->assertFalse(BestchangeAdmissionPolicy::shouldDeferNonBc(900));
        $this->assertFalse(BestchangeAdmissionPolicy::shouldDeferNonBc(2699));
        $this->assertTrue(BestchangeAdmissionPolicy::shouldDeferNonBc(2700));
        $this->assertTrue(BestchangeAdmissionPolicy::shouldDeferNonBc(5000));
    }

    public function test_last_success_age_from_guard_file(): void
    {
        $dir = sys_get_temp_dir().'/bc_admit_'.bin2hex(random_bytes(4));
        mkdir($dir);
        $path = $dir.'/last-success.compiler_bestchange';
        $epoch = 1_700_000_000;
        file_put_contents($path, "command=compiler:bestchange\nsuccess_epoch={$epoch}\n");

        $this->assertSame($epoch, BestchangeAdmissionPolicy::lastSuccessEpoch($path));
        $this->assertSame(100, BestchangeAdmissionPolicy::lastSuccessAgeSeconds($epoch + 100, $path));

        @unlink($path);
        @rmdir($dir);
    }

    public function test_schedule_uses_every_minute_expression_and_busy_only_yield(): void
    {
        $this->assertSame('* * * * *', BestchangeAdmissionPolicy::mutexCronExpression());
        $src = (string) file_get_contents(dirname(__DIR__, 2).'/app/Console/Schedule/ExchangeSchedule.php');
        $this->assertStringContainsString('BestchangeAdmissionPolicy::shouldAdmit', $src);
        $this->assertStringContainsString('bestchangeCompilerBusy', $src);
        $this->assertStringContainsString('->everyMinute()', $src);
        // Schedule must NOT defer non-BC jobs merely on generation age.
        $this->assertStringNotContainsString('shouldDeferNonBc($age)', $src);
        $this->assertStringNotContainsString(
            'return ! BestchangeAdmissionPolicy::shouldDeferNonBc($age);',
            $src
        );
    }
}
