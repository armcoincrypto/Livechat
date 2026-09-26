<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Console\Commands\RatesBestChangeDispatchWatchCommand;
use PHPUnit\Framework\TestCase;

final class BestChangeDispatchWatchContractTest extends TestCase
{
    public function test_slo_constants_preserve_1800_critical(): void
    {
        $this->assertSame(900, RatesBestChangeDispatchWatchCommand::HEARTBEAT_TARGET_SECONDS);
        $this->assertSame(1200, RatesBestChangeDispatchWatchCommand::HEARTBEAT_WARNING_SECONDS);
        $this->assertSame(1800, RatesBestChangeDispatchWatchCommand::HEARTBEAT_CRITICAL_SECONDS);
        $this->assertSame(20, RatesBestChangeDispatchWatchCommand::BC_MUTEX_TTL_MINUTES);
        $this->assertSame(1200, RatesBestChangeDispatchWatchCommand::BC_MUTEX_TTL_MINUTES * 60);
        $this->assertLessThanOrEqual(
            RatesBestChangeDispatchWatchCommand::HEARTBEAT_CRITICAL_SECONDS,
            RatesBestChangeDispatchWatchCommand::BC_MUTEX_TTL_MINUTES * 60
        );
    }

    public function test_mutex_ttl_covers_observed_runtime_ceiling(): void
    {
        $observedMaxRuntimeSec = 845;
        $this->assertGreaterThan(
            $observedMaxRuntimeSec,
            RatesBestChangeDispatchWatchCommand::BC_MUTEX_TTL_MINUTES * 60
        );
    }
}
