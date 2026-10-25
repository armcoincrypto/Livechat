<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Console\Schedule\BestchangeAdmissionPolicy;
use App\Console\Schedule\ExchangeSchedule;
use PHPUnit\Framework\TestCase;

final class ExchangeScheduleBcAdmissionTest extends TestCase
{
    public function test_bc_admission_offset_clears_boundary_and_competitor_collision(): void
    {
        $this->assertSame(6, ExchangeSchedule::BESTCHANGE_ADMISSION_OFFSET_MINUTES);
        $this->assertSame(
            '6,36 * * * *',
            ExchangeSchedule::cronExpressionForMinutesWithOffset(
                30,
                ExchangeSchedule::BESTCHANGE_ADMISSION_OFFSET_MINUTES
            )
        );
        $this->assertSame(
            '*/30 * * * *',
            ExchangeSchedule::cronExpressionForMinutes(30)
        );
        // Courses stays on operator cadence; BC nominal offset is not the same instant.
        $this->assertNotSame(
            ExchangeSchedule::cronExpressionForMinutes(30),
            ExchangeSchedule::cronExpressionForMinutesWithOffset(
                30,
                ExchangeSchedule::BESTCHANGE_ADMISSION_OFFSET_MINUTES
            )
        );
    }

    public function test_bc_offset_avoids_competitors_and_generate_prices_minutes(): void
    {
        $offset = ExchangeSchedule::BESTCHANGE_ADMISSION_OFFSET_MINUTES;
        $bcMinutes = [$offset, 30 + $offset];

        // competitors-only = 3-57/2
        $competitorMinutes = range(3, 57, 2);
        foreach ($bcMinutes as $m) {
            $this->assertNotContains(
                $m,
                $competitorMinutes,
                "BC minute {$m} must not collide with competitors-only 3-57/2"
            );
        }

        // generate_prices = everyFiveMinutes → */5
        $generatePricesMinutes = range(0, 55, 5);
        foreach ($bcMinutes as $m) {
            $this->assertNotContains(
                $m,
                $generatePricesMinutes,
                "BC minute {$m} must not collide with generate_prices */5"
            );
        }

        // Explicit rejection of blind 5,35 recommendation.
        $this->assertNotSame(5, $offset);
        $this->assertNotSame(
            '5,35 * * * *',
            ExchangeSchedule::cronExpressionForMinutesWithOffset(30, $offset)
        );
        // Interim live +4 superseded by +6 + overdue catch-up.
        $this->assertNotSame(4, $offset);
    }

    public function test_competitors_expression_avoids_minute_59_and_29(): void
    {
        $this->assertSame('3-57/2 * * * *', ExchangeSchedule::COMPETITORS_CRON_EXPRESSION);
        $this->assertStringNotContainsString('1-59/2', ExchangeSchedule::COMPETITORS_CRON_EXPRESSION);
        // Nominal BC (+6) must not collide with competitors :05.
        $this->assertStringContainsString('3-57/2', ExchangeSchedule::COMPETITORS_CRON_EXPRESSION);
        $this->assertFalse(BestchangeAdmissionPolicy::isNominalAdmissionMinute(5, 30, 6));
    }

    public function test_register_compiler_source_uses_admission_policy_and_when_bc_idle(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2).'/app/Console/Schedule/ExchangeSchedule.php');
        $this->assertStringContainsString('BestchangeAdmissionPolicy', $src);
        $this->assertStringContainsString('BESTCHANGE_ADMISSION_OFFSET_MINUTES', $src);
        $this->assertStringContainsString('->when($whenBcIdle)', $src);
        $this->assertStringContainsString('->when($whenBcAdmit)', $src);
        $this->assertStringContainsString('compiler:courses', $src);
        $this->assertStringContainsString('->everyMinute()', $src);
        $this->assertStringContainsString('bestchange-compiler.lock', $src);
        $this->assertStringContainsString('register BC BEFORE courses', $src);
        // Wave1/Wave2-interim hard cron offset alone is no longer the sole admission path.
        $this->assertStringNotContainsString(
            'cronExpressionForMinutesWithOffset($ratesMinutes',
            $src
        );
    }

    public function test_l1_and_book_reuse_markers_preserved(): void
    {
        $root = dirname(__DIR__, 2);
        $this->assertFileExists($root.'/app/Services/Rates/BestChangeL1Authority.php');
        $this->assertFileExists($root.'/app/Services/Rates/BestChangeL1Shadow.php');
        $topn = (string) file_get_contents($root.'/tests/Unit/Rates/BestChangeL1TopNBookReuseParityTest.php');
        $this->assertStringContainsString('BookReuse', $topn);
    }

    public function test_no_duplicate_bc_registration_in_schedule_source(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2).'/app/Console/Schedule/ExchangeSchedule.php');
        // Single scheduled exec registration (other mentions are comments/docs).
        $this->assertSame(
            1,
            substr_count($src, 'compilerWrapper} compiler:bestchange'),
            'Exactly one compiler:bestchange schedule exec registration expected'
        );
    }
}
