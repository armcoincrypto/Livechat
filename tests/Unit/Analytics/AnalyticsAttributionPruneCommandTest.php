<?php

declare(strict_types=1);

namespace Tests\Unit\Analytics;

use App\Console\Commands\AnalyticsAttributionPruneCommand;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AnalyticsAttributionPruneCommandTest extends TestCase
{
    public function test_signature_exposes_30_day_policy_defaults(): void
    {
        $cmd = new AnalyticsAttributionPruneCommand();
        $ref = new ReflectionClass($cmd);
        $prop = $ref->getProperty('signature');
        $prop->setAccessible(true);
        $sig = (string) $prop->getValue($cmd);

        self::assertStringContainsString('analytics:attribution-prune', $sig);
        self::assertStringContainsString('--days=30', $sig);
        self::assertStringContainsString('--batch=500', $sig);
        self::assertStringContainsString('--max-rows=5000', $sig);
        self::assertStringContainsString('--dry-run', $sig);
    }

    public function test_schedule_registers_daily_attribution_prune(): void
    {
        $schedule = file_get_contents(__DIR__.'/../../../app/Console/Schedule/ExchangeSchedule.php');
        self::assertNotFalse($schedule);
        self::assertStringContainsString(
            'analytics:attribution-prune --days=30 --batch=500 --max-rows=5000',
            $schedule
        );
        self::assertStringContainsString('withoutOverlapping(50)', $schedule);
    }
}
