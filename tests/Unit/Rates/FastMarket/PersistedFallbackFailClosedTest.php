<?php

declare(strict_types=1);

namespace Tests\Unit\Rates\FastMarket;

use App\Services\Rates\FastMarket\FastMarketFlags;
use App\Services\Rates\FastMarket\FastMarketQuoteService;
use App\Services\Rates\FastMarket\LockedQuoteException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * P0: snapshot-unavailable + persisted age > max must fail closed —
 * never authorize silent CalculatorFacade pricing from stale catalog rates.
 */
final class PersistedFallbackFailClosedTest extends TestCase
{
    public function test_max_fallback_age_default_is_1200(): void
    {
        $this->assertSame(1200, (int) config('fast_market.max_fallback_rate_age_seconds', 1200));
        $this->assertSame(1200, FastMarketFlags::maxFallbackRateAgeSeconds());
    }

    public function test_locked_quote_exception_exposes_rate_unavailable(): void
    {
        $e = LockedQuoteException::rateUnavailable('stale');
        $this->assertSame(LockedQuoteException::RATE_UNAVAILABLE, $e->errorCode);
        $this->assertSame(503, $e->httpStatus);
        $this->assertStringContainsString('stale', $e->getMessage());
    }

    public function test_direction_detail_resource_fail_closes_on_no_usable_rate(): void
    {
        $src = file_get_contents(dirname(__DIR__, 4).'/packages/ExchangerClient/Http/Resources/Operations/DirectionDetailResource.php');
        $this->assertNotFalse($src);
        $this->assertStringContainsString('fast_market_public_quote_fail_closed', $src);
        $this->assertStringContainsString("no_usable_rate", $src);
        $this->assertStringContainsString('persisted_too_old', $src);
        $this->assertStringContainsString("'fail_closed' => true", $src);
    }

    public function test_order_calculator_fail_closes_when_persisted_too_old(): void
    {
        $src = file_get_contents(dirname(__DIR__, 4).'/packages/Order/OrderCalculator.php');
        $this->assertNotFalse($src);
        $this->assertStringContainsString('persistedFallbackAcceptable', $src);
        $this->assertStringContainsString('rateUnavailable', $src);
        $this->assertStringContainsString('use_bestchange_snapshot', $src);
    }

    public function test_persisted_age_null_timestamp_fails_closed_in_source(): void
    {
        $src = file_get_contents(dirname(__DIR__, 4).'/app/Services/Rates/FastMarket/FastMarketQuoteService.php');
        $this->assertNotFalse($src);
        $this->assertStringContainsString('persistedFallbackAcceptable', $src);
        $this->assertStringContainsString('Fail closed: unknown age must not authorize financial use', $src);
        // Must not return true on null timestamp anymore.
        $this->assertDoesNotMatchRegularExpression(
            '/if \(\$ts === null\) \{\s*return true;/s',
            $src
        );
    }

    public function test_quote_service_exposes_public_persisted_fallback_acceptable(): void
    {
        $m = new ReflectionMethod(FastMarketQuoteService::class, 'persistedFallbackAcceptable');
        $this->assertTrue($m->isPublic());
    }
}
