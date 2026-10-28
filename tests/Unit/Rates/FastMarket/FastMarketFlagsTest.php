<?php

declare(strict_types=1);

namespace Tests\Unit\Rates\FastMarket;

use App\Services\Rates\FastMarket\FastMarketFlags;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

final class FastMarketFlagsTest extends TestCase
{
    private Container $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Container;
        Container::setInstance($this->app);
        $this->app->instance('config', new Repository([
            'fast_market' => [
                'plane_enabled' => false,
                'shadow_enabled' => false,
                'public_quote_enabled' => false,
                'order_quote_enabled' => false,
                'locked_quote_enabled' => false,
                'order_quote_shadow_enabled' => false,
                'producer_daemon' => true,
                'scheduler_fallback' => false,
                'snapshot_refresh_seconds' => 30,
                'producer_lock_seconds' => 55,
                'failure_backoff_seconds' => 15,
                'failure_backoff_max_seconds' => 60,
                'fresh_seconds' => 60,
                'grace_seconds' => 120,
                'expired_seconds' => 180,
                'max_fallback_rate_age_seconds' => 1200,
                'quote_ttl_seconds' => 30,
            ],
        ]));
        // Ensure helper resolves our container
        if (! function_exists('config')) {
            $this->markTestSkipped('Laravel config() helper unavailable');
        }
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        parent::tearDown();
    }

    private function setFast(array $overrides): void
    {
        /** @var Repository $cfg */
        $cfg = $this->app->make('config');
        $cur = $cfg->get('fast_market', []);
        $cfg->set('fast_market', array_merge($cur, $overrides));
    }

    public function test_all_flags_default_false(): void
    {
        $this->assertFalse(FastMarketFlags::planeEnabled());
        $this->assertFalse(FastMarketFlags::publicQuoteEnabled());
        $this->assertFalse(FastMarketFlags::orderQuoteEnabled());
        $this->assertFalse(FastMarketFlags::lockedQuoteEnabled());
        $this->assertFalse(FastMarketFlags::orderQuoteShadowEnabled());
        $this->assertFalse(FastMarketFlags::shadowEnabled());
    }

    public function test_locked_quote_requires_plane_and_public(): void
    {
        $this->setFast(['locked_quote_enabled' => true]);
        $this->assertFalse(FastMarketFlags::lockedQuoteEnabled());

        $this->setFast([
            'plane_enabled' => true,
            'public_quote_enabled' => true,
            'locked_quote_enabled' => true,
        ]);
        $this->assertTrue(FastMarketFlags::lockedQuoteEnabled());
    }

    public function test_shadow_requires_plane(): void
    {
        $this->setFast(['shadow_enabled' => true]);
        $this->assertFalse(FastMarketFlags::shadowEnabled());

        $this->setFast(['plane_enabled' => true, 'shadow_enabled' => true]);
        $this->assertTrue(FastMarketFlags::shadowEnabled());
    }

    public function test_scheduler_admitted_only_with_fallback_when_daemon_preferred(): void
    {
        $this->setFast(['plane_enabled' => true, 'producer_daemon' => true, 'scheduler_fallback' => false]);
        $this->assertFalse(FastMarketFlags::schedulerRefreshAdmitted());

        $this->setFast(['scheduler_fallback' => true]);
        $this->assertTrue(FastMarketFlags::schedulerRefreshAdmitted());
    }

    public function test_freshness_thresholds_ordered(): void
    {
        $this->assertLessThanOrEqual(
            FastMarketFlags::graceThresholdSeconds(),
            FastMarketFlags::freshnessThresholdSeconds()
        );
        $this->assertLessThanOrEqual(
            FastMarketFlags::graceThresholdSeconds(),
            FastMarketFlags::expiredThresholdSeconds()
        );
        $this->assertSame(60, FastMarketFlags::freshnessThresholdSeconds());
        $this->assertSame(120, FastMarketFlags::graceThresholdSeconds());
        $this->assertSame(180, FastMarketFlags::expiredThresholdSeconds());
        $this->assertSame(30, FastMarketFlags::snapshotRefreshSeconds());
    }

    public function test_no_direct_env_reads_in_flags_source(): void
    {
        $src = file_get_contents(dirname(__DIR__, 4).'/app/Services/Rates/FastMarket/FastMarketFlags.php');
        $this->assertDoesNotMatchRegularExpression('/\benv\s*\(/', $src);
        $this->assertDoesNotMatchRegularExpression('/\bgetenv\s*\(/', $src);
        $this->assertStringContainsString("config('fast_market.", $src);
    }
}
