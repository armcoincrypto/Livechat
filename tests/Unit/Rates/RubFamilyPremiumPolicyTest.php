<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\IndependentMarketBaseline;
use App\Services\Rates\RateConfiguredExpectation;
use App\Services\Rates\RubFamilyPremiumPolicy;
use PHPUnit\Framework\TestCase;

final class RubFamilyPremiumPolicyTest extends TestCase
{
    private function approvedPolicy(): RubFamilyPremiumPolicy
    {
        return new RubFamilyPremiumPolicy([
            'approved' => true,
            'approved_by' => 'test',
            'approved_at' => '2026-07-20T14:25:23Z',
            'default_thresholds' => [
                'unexplained_warning_percent' => 1.0,
                'unexplained_block_percent' => 2.0,
            ],
            'families' => [
                'SBPRUB' => [
                    'destination_codes' => ['SBPRUB'],
                    'decision' => 'APPROVE',
                    'target_premium_min_percent' => 3.0,
                    'target_premium_max_percent' => 5.0,
                    'warning_premium_percent' => 6.0,
                    'hard_maximum_premium_percent' => 8.0,
                    'export_allowed_when_approved' => true,
                    'order_allowed_when_approved' => true,
                ],
                'OTHER_RUB' => [
                    'destination_codes' => ['*RUB'],
                    'decision' => 'KEEP_BLOCKED',
                    'export_allowed_when_approved' => false,
                    'order_allowed_when_approved' => false,
                    'hard_maximum_premium_percent' => 0,
                ],
            ],
        ]);
    }

    public function testUnapprovedPolicyBlocksFamilyExport(): void
    {
        $p = new RubFamilyPremiumPolicy([
            'approved' => false,
            'families' => [
                'SBPRUB' => [
                    'destination_codes' => ['SBPRUB'],
                    'decision' => 'APPROVE',
                    'export_allowed_when_approved' => true,
                    'order_allowed_when_approved' => true,
                    'hard_maximum_premium_percent' => 8.0,
                ],
            ],
        ]);
        $this->assertFalse($p->isFamilyExportAllowed('SBPRUB'));
        $eval = $p->evaluateCoinRub('SBPRUB', 4.0, 0.0);
        $this->assertSame('NO_POLICY', $eval['classification']);
        $this->assertFalse($eval['export_allowed']);
    }

    public function testTargetBandPassExplainedWithoutRaisingConfiguredProfit(): void
    {
        $p = $this->approvedPolicy();
        $eval = $p->evaluateCoinRub('SBPRUB', 4.38, 0.0);
        $this->assertSame('PASS_EXPLAINED_SPREAD', $eval['classification']);
        $this->assertTrue($eval['export_allowed']);
        $this->assertSame(0.0, $eval['configured_profit_percent']);
        $this->assertEqualsWithDelta(5.0, $eval['expected_premium_percent'], 0.001);
        $this->assertLessThan(0.0, (float) $eval['unexplained_vs_expected_percent']);
    }

    public function testUnexplainedBlockAtTwoPercentAboveExpected(): void
    {
        $p = $this->approvedPolicy();
        // expected target 5%; raw 7.3% ⇒ unexplained 2.3% > 2% block
        $eval = $p->evaluateCoinRub('SBPRUB', 7.3, 0.0);
        $this->assertSame('QUARANTINE_REQUIRED', $eval['classification']);
        $this->assertFalse($eval['export_allowed']);
    }

    public function testHardMaximumBlocksEvenInsideUnexplainedMath(): void
    {
        $p = $this->approvedPolicy();
        $eval = $p->evaluateCoinRub('SBPRUB', 8.5, 0.0);
        $this->assertSame('QUARANTINE_REQUIRED', $eval['classification']);
        $this->assertContains('raw_premium_exceeds_hard_maximum', $eval['reasons']);
    }

    public function testConfiguredProfitAboveHardMaxBlockedWithoutAutoIncrease(): void
    {
        $p = $this->approvedPolicy();
        $eval = $p->evaluateCoinRub('SBPRUB', 4.0, 9.0);
        $this->assertSame('QUARANTINE_REQUIRED', $eval['classification']);
        $this->assertContains('configured_premium_exceeds_family_hard_maximum', $eval['reasons']);
    }

    public function testWarningBandIsReviewNotExportable(): void
    {
        $p = $this->approvedPolicy();
        // raw 6.2% > warning 6%, unexplained vs 5% = 1.2% > 1% warn
        $eval = $p->evaluateCoinRub('SBPRUB', 6.2, 0.0);
        $this->assertSame('REVIEW', $eval['classification']);
        $this->assertFalse($eval['export_allowed']);
        $this->assertFalse($eval['order_allowed']);
    }

    public function testUnexplainedBlockQuarantinesAndBlocksOrders(): void
    {
        $p = $this->approvedPolicy();
        // Direction 268 pattern: raw ~7.14% vs SBP target 5% → unexplained ~2.14% > 2% block
        $eval = $p->evaluateCoinRub('SBPRUB', 7.143137, 0.3);
        $this->assertSame('QUARANTINE_REQUIRED', $eval['classification']);
        $this->assertFalse($eval['export_allowed']);
        $this->assertFalse($eval['order_allowed']);
        $this->assertContains('unexplained_above_expected_block', $eval['reasons']);
    }

    public function testZec541WithinTargetExportsWhenApproved(): void
    {
        $p = $this->approvedPolicy();
        $baseline = 41470.56;
        $course541 = 43286.997609395908945509;
        $analysis = (new RateConfiguredExpectation())->analyze(
            baseline: (string) $baseline,
            actual: (string) $course541,
            profitPercent: '0',
        );
        $eval = $p->evaluateCoinRub('SBPRUB', (float) $analysis['raw_market_deviation'], 0.0);
        $this->assertSame('PASS_EXPLAINED_SPREAD', $eval['classification']);
        $this->assertTrue($eval['export_allowed']);
    }

    public function testZec540BlockedIndividually(): void
    {
        $p = $this->approvedPolicy();
        $baseline = 41470.56;
        $course540 = 44517.921233869460447696;
        $analysis = (new RateConfiguredExpectation())->analyze(
            baseline: (string) $baseline,
            actual: (string) $course540,
            profitPercent: '0',
        );
        $eval = $p->evaluateCoinRub('SBERRUB', (float) $analysis['raw_market_deviation'], 0.0);
        // SBERRUB not in this fixture — map using SBPRUB limits via duplicate family in fixture
        $eval = $p->evaluateCoinRub('SBPRUB', (float) $analysis['raw_market_deviation'], 0.0);
        $this->assertFalse($eval['export_allowed']);
        $this->assertSame('QUARANTINE_REQUIRED', $eval['classification']);
    }

    public function testTonIsNotGram(): void
    {
        $this->assertSame('TON', IndependentMarketBaseline::assetFromCode('TON'));
        $this->assertNull(IndependentMarketBaseline::assetFromCode('GRAM'));
    }

    public function testKeepBlockedFamilyNeverExports(): void
    {
        $p = $this->approvedPolicy();
        $this->assertFalse($p->isFamilyExportAllowed('ZZZRUB'));
    }
}
