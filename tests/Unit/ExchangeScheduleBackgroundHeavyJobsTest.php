<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Console\Schedule\ExchangeSchedule;
use PHPUnit\Framework\TestCase;

/**
 * Wave3 contract: heavy rate compilers must not pin schedule:run in the foreground.
 * Wave1 contract: BC mutex TTL fallback + BC-before-courses admission fairness.
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

        // Wave1: withoutOverlapping TTL is FALLBACK only (schedule:finish releases).
        // 15m > max post-L1 wrapper hold (~6–7m) and < typical 30m rates cadence so a
        // missed finish cannot skip the next due tick (60m TTL previously caused P50≈60m).
        $this->assertSame(15, ExchangeSchedule::BESTCHANGE_WITHOUT_OVERLAPPING_MINUTES);
        $this->assertMatchesRegularExpression(
            '/compiler:bestchange[\s\S]{0,500}withoutOverlapping\(\s*self::BESTCHANGE_WITHOUT_OVERLAPPING_MINUTES\s*\)/',
            $src,
            'compiler:bestchange must use BESTCHANGE_WITHOUT_OVERLAPPING_MINUTES'
        );
        $this->assertStringContainsString('rates:bestchange-dispatch-watch', $src);
        $this->assertDoesNotMatchRegularExpression(
            '/compiler:bestchange[\s\S]{0,500}withoutOverlapping\(\s*300\s*\)/',
            $src,
            'compiler:bestchange must not retain withoutOverlapping(300) minute TTL'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/compiler:bestchange[\s\S]{0,500}withoutOverlapping\(\s*60\s*\)/',
            $src,
            'compiler:bestchange must not retain withoutOverlapping(60) minute TTL'
        );
    }

    public function test_bestchange_is_registered_before_courses_for_flock_admission(): void
    {
        $path = dirname(__DIR__, 2).'/app/Console/Schedule/ExchangeSchedule.php';
        $src = (string) file_get_contents($path);

        $bcPos = strpos($src, 'exec("{$compilerWrapper} compiler:bestchange")');
        $coursesPos = strpos($src, 'exec("{$compilerWrapper} compiler:courses --isUpdate=0")');
        $this->assertNotFalse($bcPos, 'missing compiler:bestchange registration');
        $this->assertNotFalse($coursesPos, 'missing compiler:courses registration');
        $this->assertLessThan(
            $coursesPos,
            $bcPos,
            'Wave1: compiler:bestchange must be registered before compiler:courses'
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
