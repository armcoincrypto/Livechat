<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\RubFamilyPremiumPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Locks the shipped rub-family-premium-policy.json bands used by fromStorageApp().
 */
final class RubFamilyProductionPolicyFileTest extends TestCase
{
    /**
     * @return array<string,mixed>
     */
    private function shipped(): array
    {
        $path = dirname(__DIR__, 3) . '/resources/rates/rub-family-premium-policy.json';
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);
        $this->assertTrue(!empty($decoded['approved']));

        return $decoded;
    }

    public function testShippedSberTcsRfbExplainedCeilingMatchesApprovedWarningBand(): void
    {
        $cfg = $this->shipped();
        $this->assertSame(11, (int) ($cfg['version'] ?? 0));
        $this->assertEqualsWithDelta(2.5, (float) $cfg['default_thresholds']['unexplained_block_percent'], 0.001);
        foreach (['SBERRUB', 'TCSBRUB', 'RFBRUB'] as $family) {
            $this->assertEqualsWithDelta(6.0, (float) $cfg['families'][$family]['target_premium_max_percent'], 0.001);
            $this->assertEqualsWithDelta(6.0, (float) $cfg['families'][$family]['warning_premium_percent'], 0.001);
            $this->assertEqualsWithDelta(9.0, (float) $cfg['families'][$family]['hard_maximum_premium_percent'], 0.001);
        }
        $this->assertEqualsWithDelta(4.0, (float) $cfg['families']['SBPRUB']['target_premium_max_percent'], 0.001);
        $this->assertEqualsWithDelta(4.0, (float) $cfg['families']['ACRUB']['target_premium_max_percent'], 0.001);
        $this->assertSame('MANUAL_PROFIT', $cfg['intentional_commercial']['mode']);
    }

    public function testShippedPolicyClassifiesLiveUsdtTrc20SberOtcAsReview(): void
    {
        $p = new RubFamilyPremiumPolicy($this->shipped());
        $eval = $p->evaluateCoinRub('SBERRUB', 7.71483, 1.0, 84.24456, 'USDTTRC20');
        $this->assertSame('REVIEW', $eval['classification']);
        $this->assertEqualsWithDelta(6.0, (float) $eval['expected_premium_percent'], 0.001);
        $this->assertEqualsWithDelta(1.71483, (float) $eval['unexplained_vs_expected_percent'], 0.001);
    }

    public function testMismatchedComparatorFamiliesKeepDistinctExplainedCeilings(): void
    {
        $p = new RubFamilyPremiumPolicy($this->shipped());
        $this->assertEqualsWithDelta(6.0, (float) $p->targetPremiumMaxPercent('SBERRUB'), 0.001);
        $this->assertEqualsWithDelta(4.0, (float) $p->targetPremiumMaxPercent('SBPRUB'), 0.001);
        $this->assertEqualsWithDelta(6.0, (float) $p->targetPremiumMaxPercent('TCSBRUB'), 0.001);
        $this->assertEqualsWithDelta(6.0, (float) $p->targetPremiumMaxPercent('TBRUB'), 0.001);
        $this->assertNotEquals(
            $p->targetPremiumMaxPercent('SBERRUB'),
            $p->targetPremiumMaxPercent('SBPRUB'),
            'Sber and SBP must not share one explained ceiling after the OTC-band fix'
        );
    }
}
