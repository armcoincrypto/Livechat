<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Console\Commands\RatesBestChangeDispatchWatchCommand;
use App\Console\Schedule\ExchangeSchedule;
use PHPUnit\Framework\TestCase;

final class BestChangeDispatchWatchContractTest extends TestCase
{
    public function test_slo_constants_preserve_1800_critical(): void
    {
        $this->assertSame(900, RatesBestChangeDispatchWatchCommand::HEARTBEAT_TARGET_SECONDS);
        $this->assertSame(1200, RatesBestChangeDispatchWatchCommand::HEARTBEAT_WARNING_SECONDS);
        $this->assertSame(1800, RatesBestChangeDispatchWatchCommand::HEARTBEAT_CRITICAL_SECONDS);
        $this->assertSame(
            ExchangeSchedule::BESTCHANGE_WITHOUT_OVERLAPPING_MINUTES,
            RatesBestChangeDispatchWatchCommand::BC_MUTEX_TTL_MINUTES
        );
        // Wave1: mutex TTL is a finish fallback (< rates cadence), independent of book SLO.
        $this->assertSame(15, RatesBestChangeDispatchWatchCommand::BC_MUTEX_TTL_MINUTES);
        $this->assertLessThan(
            RatesBestChangeDispatchWatchCommand::HEARTBEAT_CRITICAL_SECONDS,
            RatesBestChangeDispatchWatchCommand::BC_MUTEX_TTL_MINUTES * 60
        );
    }

    public function test_mutex_name_uses_cadence_expression_not_stale_every_minute_hint(): void
    {
        $wrapper = '/opt/exswaping-owned-ops/current/scripts/ops/run_backend_compiler.sh';
        $command = $wrapper.' compiler:bestchange';
        $staleEveryMinute = 'framework/schedule-'.sha1('* * * * *'.$command);
        $thirty = 'framework/schedule-'.sha1(ExchangeSchedule::cronExpressionForMinutes(30).$command);
        $admitted = 'framework/schedule-'.sha1(
            ExchangeSchedule::cronExpressionForMinutesWithOffset(
                30,
                ExchangeSchedule::BESTCHANGE_ADMISSION_OFFSET_MINUTES
            ).$command
        );

        $this->assertSame('framework/schedule-b764b2e2eab9d758d93f4bef3cf925f00c02be28', $staleEveryMinute);
        $this->assertSame('framework/schedule-9311b9f95b58be393fe0b1db8fc97ddfcca25c9b', $thirty);
        $this->assertNotSame($staleEveryMinute, $thirty);
        $this->assertNotSame($thirty, $admitted);
        $this->assertSame('*/30 * * * *', ExchangeSchedule::cronExpressionForMinutes(30));
        $this->assertSame('2,32 * * * *', ExchangeSchedule::cronExpressionForMinutesWithOffset(30, 2));
    }

    public function test_mutex_ttl_covers_post_l1_wrapper_hold_not_legacy_30m_runtime(): void
    {
        // Post-L1 wrapper hold ~340–376s; TTL 15m must remain above that.
        $observedPostL1HoldSec = 376;
        $this->assertGreaterThan(
            $observedPostL1HoldSec,
            RatesBestChangeDispatchWatchCommand::BC_MUTEX_TTL_MINUTES * 60
        );
    }

    public function test_resolve_mutex_follows_admission_offset(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/app/Console/Commands/RatesBestChangeDispatchWatchCommand.php');
        $this->assertStringContainsString('cronExpressionForMinutesWithOffset', $src);
        $this->assertStringContainsString('BESTCHANGE_ADMISSION_OFFSET_MINUTES', $src);
    }

}
