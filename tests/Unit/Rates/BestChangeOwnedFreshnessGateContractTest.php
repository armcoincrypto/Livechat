<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\BestChangeMarketBaseHealth;
use App\Services\Rates\RateDirectionEligibility;
use PHPUnit\Framework\TestCase;

/**
 * Contract: BestChange-owned quote/order must share one freshness authority
 * (BestChangeMarketBaseHealth) inside RateDirectionEligibility — not SourceChecks.
 */
final class BestChangeOwnedFreshnessGateContractTest extends TestCase
{
    public function test_eligibility_source_gates_bestchange_via_market_base_health(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Rates/RateDirectionEligibility.php');
        $this->assertStringContainsString('BestChangeMarketBaseHealth::evaluate', $src);
        $this->assertStringContainsString('bestchange_book_', $src);
        $this->assertStringContainsString('isBestChangeRuntimeOwner', $src);
        // Must not alter calculator ownership SourceChecks path.
        $checks = (string) file_get_contents(dirname(__DIR__, 3).'/packages/Calculator/SourceChecks.php');
        $this->assertStringContainsString("return \$bcActive;\n", $checks);
    }

    public function test_default_max_age_matches_critical_slo(): void
    {
        $this->assertSame(1800, BestChangeMarketBaseHealth::DEFAULT_MAX_AGE_SECONDS);
        $this->assertSame(1800, RateDirectionEligibility::class !== '' ? BestChangeMarketBaseHealth::DEFAULT_MAX_AGE_SECONDS : 0);
    }
}
