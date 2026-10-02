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
        $this->assertSame(60, RatesBestChangeDispatchWatchCommand::BC_MUTEX_TTL_MINUTES);
        $this->assertSame(3600, RatesBestChangeDispatchWatchCommand::BC_MUTEX_TTL_MINUTES * 60);
        // Mutex TTL must exceed observed max compiler runtime; heartbeat critical (30m)
        // remains an independent book-freshness SLO and is no longer a TTL ceiling.
        $this->assertGreaterThan(
            RatesBestChangeDispatchWatchCommand::HEARTBEAT_CRITICAL_SECONDS,
            RatesBestChangeDispatchWatchCommand::BC_MUTEX_TTL_MINUTES * 60
        );
    }

    public function test_mutex_ttl_covers_observed_runtime_ceiling(): void
    {
        // Observed max 2026-10-02 ≈ 1754s (~29.2m); keep ceiling with margin.
        $observedMaxRuntimeSec = 1754;
        $this->assertGreaterThan(
            $observedMaxRuntimeSec,
            RatesBestChangeDispatchWatchCommand::BC_MUTEX_TTL_MINUTES * 60
        );
    }
}
