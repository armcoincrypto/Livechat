<?php

declare(strict_types=1);

namespace Tests\Unit\Rates\FastMarket;

use App\Services\Rates\FastMarket\FastMarketFlags;
use PHPUnit\Framework\TestCase;

final class FastMarketFlagsTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach ([
            FastMarketFlags::PLANE,
            FastMarketFlags::PUBLIC_QUOTE,
            FastMarketFlags::ORDER_QUOTE,
            FastMarketFlags::SHADOW,
        ] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
        parent::tearDown();
    }

    public function test_all_flags_default_false(): void
    {
        $this->assertFalse(FastMarketFlags::planeEnabled());
        $this->assertFalse(FastMarketFlags::publicQuoteEnabled());
        $this->assertFalse(FastMarketFlags::orderQuoteEnabled());
        $this->assertFalse(FastMarketFlags::shadowEnabled());
    }

    public function test_shadow_requires_plane(): void
    {
        putenv(FastMarketFlags::SHADOW.'=true');
        $_ENV[FastMarketFlags::SHADOW] = 'true';
        $this->assertFalse(FastMarketFlags::shadowEnabled());

        putenv(FastMarketFlags::PLANE.'=true');
        $_ENV[FastMarketFlags::PLANE] = 'true';
        $this->assertTrue(FastMarketFlags::shadowEnabled());
    }

    public function test_freshness_thresholds_ordered(): void
    {
        $this->assertLessThanOrEqual(
            FastMarketFlags::graceThresholdSeconds(),
            FastMarketFlags::freshnessThresholdSeconds()
        );
        $this->assertLessThanOrEqual(
            FastMarketFlags::expiredThresholdSeconds(),
            // grace <= expired
            FastMarketFlags::expiredThresholdSeconds()
        );
        $this->assertLessThanOrEqual(
            FastMarketFlags::graceThresholdSeconds(),
            FastMarketFlags::expiredThresholdSeconds()
        );
        $this->assertSame(60, FastMarketFlags::freshnessThresholdSeconds());
        $this->assertSame(120, FastMarketFlags::graceThresholdSeconds());
        $this->assertSame(180, FastMarketFlags::expiredThresholdSeconds());
    }
}
