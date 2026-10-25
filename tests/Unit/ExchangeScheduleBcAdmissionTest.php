<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Console\Schedule\ExchangeSchedule;
use PHPUnit\Framework\TestCase;

final class ExchangeScheduleBcAdmissionTest extends TestCase
{
    public function test_bc_admission_offset_clears_boundary_collision(): void
    {
        $this->assertSame(2, ExchangeSchedule::BESTCHANGE_ADMISSION_OFFSET_MINUTES);
        $this->assertSame(
            '2,32 * * * *',
            ExchangeSchedule::cronExpressionForMinutesWithOffset(30, 2)
        );
        $this->assertSame(
            '*/30 * * * *',
            ExchangeSchedule::cronExpressionForMinutes(30)
        );
        // Courses stays on operator cadence; BC is offset — not the same instant.
        $this->assertNotSame(
            ExchangeSchedule::cronExpressionForMinutes(30),
            ExchangeSchedule::cronExpressionForMinutesWithOffset(30, 2)
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
    }

    public function test_l1_and_book_reuse_markers_preserved(): void
    {
        $root = dirname(__DIR__, 2);
        $this->assertFileExists($root.'/app/Services/Rates/BestChangeL1Authority.php');
        $this->assertFileExists($root.'/app/Services/Rates/BestChangeL1Shadow.php');
        $topn = (string) file_get_contents($root.'/tests/Unit/Rates/BestChangeL1TopNBookReuseParityTest.php');
        $this->assertStringContainsString('BookReuse', $topn);
    }
}
