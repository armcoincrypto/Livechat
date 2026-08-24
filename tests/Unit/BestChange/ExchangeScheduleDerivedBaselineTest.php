<?php
declare(strict_types=1);

namespace Tests\Unit\BestChange;

use App\Console\Commands\DirectionsDerivedBaselineRefreshCommand;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ExchangeScheduleDerivedBaselineTest extends TestCase
{
    public function test_schedule_owns_derived_baseline_refresh_with_overlap_protection(): void
    {
        $schedule = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Console/Schedule/ExchangeSchedule.php');
        $this->assertStringContainsString("directions:derived-baseline-refresh", $schedule);
        $this->assertStringNotContainsString("directions:derived-baseline-refresh --apply", $schedule);
        $this->assertStringContainsString('everyTenMinutes()', $schedule);
        $this->assertStringContainsString('withoutOverlapping(9)', $schedule);
        $this->assertStringContainsString('derived_baseline_refresh.log', $schedule);
        $this->assertSame(1, substr_count($schedule, 'directions:derived-baseline-refresh'));
    }

    public function test_command_defaults_to_dry_run_and_exposes_full_flag(): void
    {
        $cmd = new DirectionsDerivedBaselineRefreshCommand();
        $ref = new ReflectionClass($cmd);
        $prop = $ref->getProperty('signature');
        $prop->setAccessible(true);
        $sig = (string) $prop->getValue($cmd);
        $this->assertStringContainsString('directions:derived-baseline-refresh', $sig);
        $this->assertStringContainsString('--apply', $sig);
        $this->assertStringContainsString('--full', $sig);
        $this->assertStringContainsString('--dry-run', $sig);
        $this->assertStringContainsString('--ids=', $sig);
    }
}
