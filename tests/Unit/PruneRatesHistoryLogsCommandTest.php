<?php

declare(strict_types=1);

namespace Tests\Unit;

use iEXPackages\Courses\Console\PruneRatesHistoryLogsCommand;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PruneRatesHistoryLogsCommandTest extends TestCase
{
    public function test_signature_exposes_safe_defaults(): void
    {
        $cmd = new PruneRatesHistoryLogsCommand();
        $signature = $cmd->getName() ?? '';
        // Laravel stores name separately; assert signature string via reflection.
        $ref = new ReflectionClass($cmd);
        $prop = $ref->getProperty('signature');
        $prop->setAccessible(true);
        $sig = (string) $prop->getValue($cmd);

        self::assertStringContainsString('rates-history:prune', $sig);
        self::assertStringContainsString('--days=90', $sig);
        self::assertStringContainsString('--batch=5000', $sig);
        self::assertStringContainsString('--max-rows=50000', $sig);
        self::assertStringContainsString('--skip-count', $sig);
        self::assertStringContainsString('--dry-run', $sig);
    }

    public function test_command_is_registered_in_courses_provider_source(): void
    {
        $provider = file_get_contents(__DIR__ . '/../../packages/Courses/CoursesServiceProvider.php');
        self::assertNotFalse($provider);
        self::assertStringContainsString('PruneRatesHistoryLogsCommand::class', $provider);
    }

    public function test_schedule_registers_bounded_hourly_prune(): void
    {
        $schedule = file_get_contents(__DIR__ . '/../../app/Console/Schedule/ExchangeSchedule.php');
        self::assertNotFalse($schedule);
        self::assertStringContainsString("rates-history:prune --days=90 --batch=5000 --max-rows=50000", $schedule);
        self::assertStringContainsString('withoutOverlapping(50)', $schedule);
    }
}
