<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Wave3 contract: heavy rate compilers must not pin schedule:run in the foreground.
 */
final class ExchangeScheduleBackgroundHeavyJobsTest extends TestCase
{
    public function test_heavy_compiler_events_declare_run_in_background(): void
    {
        $path = dirname(__DIR__, 2).'/app/Console/Schedule/ExchangeSchedule.php';
        $src = (string) file_get_contents($path);

        foreach ([
            'compiler:courses',
            'compiler:bestchange',
            'scheme:files',
            'rates:zelleusd-usdt-preview',
            'merchant:webhook',
        ] as $needle) {
            $pos = strpos($src, $needle);
            $this->assertNotFalse($pos, "missing schedule entry for {$needle}");
            $window = substr($src, $pos, 450);
            $this->assertStringContainsString(
                'runInBackground',
                $window,
                "{$needle} must call runInBackground near registration"
            );
        }

        $this->assertMatchesRegularExpression(
            '/compiler:bestchange[\s\S]{0,400}withoutOverlapping\(\s*300\s*\)/',
            $src,
            'compiler:bestchange lock TTL must cover observed ~250s runtime'
        );
    }
}
