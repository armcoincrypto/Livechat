<?php

declare(strict_types=1);

namespace Tests\Unit\Rates\FastMarket;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Pure scheduling math for the producer loop (no network).
 */
final class ProducerLoopSchedulingTest extends TestCase
{
    public function test_next_boundary_math(): void
    {
        $interval = 30;
        $now = 1_700_000_015; // 15s into a window
        $next = (intdiv($now, $interval) + 1) * $interval;
        $this->assertSame(1_700_000_030, $next);
        $this->assertSame(15, $next - $now);

        $aligned = 1_700_000_030;
        $next2 = (intdiv($aligned, $interval) + 1) * $interval;
        $this->assertSame(1_700_000_060, $next2);
    }

    public function test_producer_loop_command_exists_and_documents_once(): void
    {
        $ref = new ReflectionClass(\App\Console\Commands\RatesFastMarketProducerLoopCommand::class);
        $this->assertTrue($ref->hasMethod('handle'));
        $src = file_get_contents($ref->getFileName());
        $this->assertStringContainsString('sleepUntilBoundary', $src);
        $this->assertStringContainsString('failureBackoff', $src);
        $this->assertStringContainsString('--once', $src);
    }

    public function test_config_file_is_sole_env_reader_for_fast_market_keys(): void
    {
        $cfg = file_get_contents(dirname(__DIR__, 4).'/config/fast_market.php');
        $this->assertStringContainsString("env('FAST_MARKET_PLANE_ENABLED'", $cfg);
        $this->assertStringContainsString("env('FAST_MARKET_SNAPSHOT_REFRESH_SECONDS'", $cfg);

        $dir = dirname(__DIR__, 4).'/app/Services/Rates/FastMarket';
        foreach (glob($dir.'/*.php') as $file) {
            if (str_ends_with($file, 'FastMarketFlags.php')) {
                $src = file_get_contents($file);
                $this->assertDoesNotMatchRegularExpression('/\benv\s*\(/', $src);
                continue;
            }
            // Other FastMarket services must not introduce new env() flag reads
            $src = file_get_contents($file);
            if (preg_match('/FAST_MARKET_|FAST_PUBLIC_|FAST_ORDER_/', $src)) {
                $this->assertDoesNotMatchRegularExpression('/env\s*\(\s*[\'"]FAST_/', $src, basename($file));
            }
        }
    }
}
