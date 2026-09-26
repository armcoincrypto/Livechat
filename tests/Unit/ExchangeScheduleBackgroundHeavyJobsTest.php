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

        // Laravel withoutOverlapping($n) is MINUTES (CacheEventMutex: expiresAt * 60).
        // Observed BC runtime ~780–845s (~13–14m); TTL must cover that with headroom,
        // but must NOT be 300 minutes (5h) which caused multi-tens-of-minutes silent gaps.
        $this->assertMatchesRegularExpression(
            '/compiler:bestchange[\s\S]{0,500}withoutOverlapping\(\s*20\s*\)/',
            $src,
            'compiler:bestchange mutex TTL must be 20 minutes (not 300)'
        );
        $this->assertStringContainsString('rates:bestchange-dispatch-watch', $src);
        $this->assertDoesNotMatchRegularExpression(
            '/compiler:bestchange[\s\S]{0,500}withoutOverlapping\(\s*300\s*\)/',
            $src,
            'compiler:bestchange must not retain withoutOverlapping(300) minute TTL'
        );
    }

    public function test_laravel_without_overlapping_unit_is_minutes_in_framework(): void
    {
        $mutex = dirname(__DIR__, 2).'/vendor/laravel/framework/src/Illuminate/Console/Scheduling/CacheEventMutex.php';
        $pending = dirname(__DIR__, 2).'/vendor/laravel/framework/src/Illuminate/Console/Scheduling/PendingEventAttributes.php';
        if (! is_file($mutex) || ! is_file($pending)) {
            $this->markTestSkipped('framework vendor not present in this checkout');
        }
        $mutexSrc = (string) file_get_contents($mutex);
        $pendingSrc = (string) file_get_contents($pending);
        $this->assertStringContainsString('expiresAt * 60', $mutexSrc);
        $this->assertStringContainsString('in minutes', $pendingSrc);
    }
}
