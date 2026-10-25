<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Console\Schedule\ExchangeSchedule;
use PHPUnit\Framework\TestCase;

final class ExchangeScheduleBcAdmissionTest extends TestCase
{
    public function test_bc_admission_offset_clears_boundary_and_competitor_collision(): void
    {
        $this->assertSame(4, ExchangeSchedule::BESTCHANGE_ADMISSION_OFFSET_MINUTES);
        $this->assertSame(
            '4,34 * * * *',
            ExchangeSchedule::cronExpressionForMinutesWithOffset(
                30,
                ExchangeSchedule::BESTCHANGE_ADMISSION_OFFSET_MINUTES
            )
        );
        $this->assertSame(
            '*/30 * * * *',
            ExchangeSchedule::cronExpressionForMinutes(30)
        );
        // Courses stays on operator cadence; BC is offset — not the same instant.
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
    }

    public function test_competitors_expression_avoids_minute_59_and_29(): void
    {
        $this->assertSame('3-57/2 * * * *', ExchangeSchedule::COMPETITORS_CRON_EXPRESSION);
        $this->assertStringNotContainsString('1-59/2', ExchangeSchedule::COMPETITORS_CRON_EXPRESSION);
    }

    public function test_register_compiler_source_uses_offset_and_when_bc_idle_for_courses(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2).'/app/Console/Schedule/ExchangeSchedule.php');
        $this->assertStringContainsString('cronExpressionForMinutesWithOffset', $src);
        $this->assertStringContainsString('BESTCHANGE_ADMISSION_OFFSET_MINUTES', $src);
        $this->assertStringContainsString('->when($whenBcIdle)', $src);
        $this->assertStringContainsString('compiler:courses', $src);
        // Geometry-only: shared flock + serialization markers unchanged.
        $this->assertStringContainsString('bestchange-compiler.lock', $src);
        $this->assertStringContainsString('register BC BEFORE courses', $src);
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
