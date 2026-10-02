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
            // Prefer the schedule->exec/command registration site, not earlier comments.
            $pos = strpos($src, "exec(\"{\$compilerWrapper} {$needle}\")");
            if ($pos === false) {
                $pos = strpos($src, "exec(\"{\$compilerWrapper} {$needle} ");
            }
            if ($pos === false) {
                $pos = strrpos($src, $needle);
            }
            $this->assertNotFalse($pos, "missing schedule entry for {$needle}");
            $window = substr($src, max(0, $pos - 80), 550);
            $this->assertStringContainsString(
                'runInBackground',
                $window,
                "{$needle} must call runInBackground near registration"
            );
        }

        // Laravel withoutOverlapping($n) is MINUTES (CacheEventMutex: expiresAt * 60).
        // Observed BC runtime 2026-10-02: avg ~20.6m / max ~29.2m. TTL 60 covers that
        // with headroom; must NOT be 300 minutes (5h) which caused silent gaps.
        $this->assertMatchesRegularExpression(
            '/compiler:bestchange[\s\S]{0,500}withoutOverlapping\(\s*60\s*\)/',
            $src,
            'compiler:bestchange mutex TTL must be 60 minutes (not 300)'
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
