<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Console\Commands\DirectionsDerivedBaselineRefreshCommand;
use App\Services\Rates\BestChangeMarketBaseHealth;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * BC status=1 is a hard derived-write boundary regardless of health.
 * These tests do not open the production database.
 */
final class DerivedMarketBaselineBcActiveOwnershipTest extends TestCase
{
    public function test_active_status_is_exactly_one(): void
    {
        $this->assertTrue(BestChangeMarketBaseHealth::isActiveStatus(1));
        $this->assertTrue(BestChangeMarketBaseHealth::isActiveStatus('1'));
        $this->assertFalse(BestChangeMarketBaseHealth::isActiveStatus(0));
        $this->assertFalse(BestChangeMarketBaseHealth::isActiveStatus('0'));
        $this->assertFalse(BestChangeMarketBaseHealth::isActiveStatus(null));
        $this->assertFalse(BestChangeMarketBaseHealth::isActiveStatus(2));
    }

    public function test_apply_skips_on_active_link_not_merely_healthy_link(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Services/Rates/DerivedMarketBaselineAuthority.php');
        $apply = $this->extractMethod($src, 'apply');
        $this->assertStringContainsString('BestChangeMarketBaseHealth::hasActiveLink($directionId)', $apply);
        $this->assertStringContainsString("'skipped' => 'bestchange_active'", $apply);
        $this->assertStringNotContainsString('isHealthy($directionId)', $apply);
        $this->assertStringNotContainsString('!$blockBc && BestChangeMarketBaseHealth::isHealthy', $apply);
        $this->assertStringContainsString('ZelleOutgoingRateWriteGuard::denyGenericWrite', $apply);
    }

    public function test_health_helper_distinguishes_active_link_from_healthy(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Services/Rates/BestChangeMarketBaseHealth.php');
        $this->assertStringContainsString('function hasActiveLink', $src);
        $this->assertStringContainsString('function isActiveStatus', $src);
        $this->assertStringContainsString("->value('status')", $src);
        $healthy = $this->extractMethod($src, 'isHealthy');
        $this->assertStringContainsString("['healthy'] === true", $healthy);
        $active = $this->extractMethod($src, 'hasActiveLink');
        $this->assertStringNotContainsString('isHealthy', $active);
        $this->assertStringNotContainsString('evaluate(', $active);
    }

    public function test_command_accepts_ids_and_defaults_empty_to_all(): void
    {
        $cmd = new DirectionsDerivedBaselineRefreshCommand();
        $ref = new ReflectionClass($cmd);
        $sig = (string) $ref->getProperty('signature')->getValue($cmd);
        $this->assertStringContainsString('--ids=', $sig);

        $m = $ref->getMethod('parseIds');
        $m->setAccessible(true);
        $this->assertNull($m->invoke($cmd, ''));
        $this->assertNull($m->invoke($cmd, '   '));
        $this->assertSame([1665, 1249], $m->invoke($cmd, '1665, 1249'));
        $this->assertSame([], $m->invoke($cmd, 'abc'));
    }

    public function test_schedule_still_dry_run_without_apply(): void
    {
        $schedule = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Console/Schedule/ExchangeSchedule.php');
        $this->assertStringContainsString("directions:derived-baseline-refresh", $schedule);
        $this->assertStringNotContainsString("directions:derived-baseline-refresh --apply", $schedule);
    }

    private function extractMethod(string $src, string $name): string
    {
        $needle = 'function ' . $name;
        $start = strpos($src, $needle);
        $this->assertNotFalse($start, $name . ' missing');
        $brace = strpos($src, '{', $start);
        $depth = 0;
        $end = $brace;
        $len = strlen($src);
        for ($i = $brace; $i < $len; $i++) {
            $ch = $src[$i];
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }

        return substr($src, $start, $end - $start + 1);
    }
}
