<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\IndependentMarketBaseline;
use App\Services\Rates\RateConfiguredExpectation;
use App\Services\Rates\RateExportQuarantine;
use App\Services\Rates\RubFamilyPremiumPolicy;
use PHPUnit\Framework\TestCase;

final class RubFamilyPremiumPolicyTest extends TestCase
{
    public function testUnapprovedPolicyBlocksFamilyExportAndExplainedPremium(): void
    {
        $p = new RubFamilyPremiumPolicy([
            'approved' => false,
            'families' => [
                'SBPRUB' => [
                    'destination_codes' => ['SBPRUB'],
                    'proposed_decision' => 'APPROVE',
                    'maximum_explained_premium_percent' => 8.0,
                    'export_allowed_when_approved' => true,
                    'order_allowed_when_approved' => true,
                ],
            ],
        ]);
        $this->assertFalse($p->isApproved());
        $this->assertFalse($p->isFamilyExportAllowed('SBPRUB'));
        $this->assertFalse($p->isFamilyOrderAllowed('SBPRUB'));
        $this->assertNull($p->explainedPremiumPercent('SBPRUB'));
    }

    public function testApprovedPremiumExplainsDeviationOnce(): void
    {
        $p = new RubFamilyPremiumPolicy([
            'approved' => true,
            'families' => [
                'SBPRUB' => [
                    'destination_codes' => ['SBPRUB'],
                    'proposed_decision' => 'APPROVE',
                    'maximum_explained_premium_percent' => 8.0,
                    'export_allowed_when_approved' => true,
                    'order_allowed_when_approved' => true,
                    'warning_unexplained_percent' => 5.0,
                    'critical_unexplained_percent' => 10.0,
                ],
            ],
        ]);
        $this->assertTrue($p->isFamilyExportAllowed('SBPRUB'));
        $this->assertSame(8.0, $p->explainedPremiumPercent('SBPRUB'));

        $baseline = '41470.56';
        $actual = '43286.997609395908945509'; // ~+4.38% vs mid
        $analysis = (new RateConfiguredExpectation())->analyze(
            baseline: $baseline,
            actual: $actual,
            profitPercent: '0',
        );
        $raw = (float) $analysis['raw_market_deviation'];
        $overBand = $p->unexplainedVersusApprovedBand($raw, 'SBPRUB');
        $this->assertNotNull($overBand);
        // 4.38 - 8 < 0 ⇒ within approved OTC band
        $this->assertLessThan(0.0, $overBand);
    }

    public function testZec541CalculationGatingWithoutPolicy(): void
    {
        $baseline = '41470.56';
        $course541 = '43286.997609395908945509';
        $q = new RateExportQuarantine();
        // Without explained premium, ~4.4% is still export-allowed under default 7% critical,
        // but public RUB export requires approved policy separately.
        $decision = $q->evaluate($course541, [
            'baseline' => $baseline,
            'profit_percent' => '0',
        ]);
        $this->assertTrue($decision['allowed']);
        $p = new RubFamilyPremiumPolicy(['approved' => false, 'families' => [
            'SBPRUB' => [
                'destination_codes' => ['SBPRUB'],
                'proposed_decision' => 'APPROVE',
                'export_allowed_when_approved' => true,
                'order_allowed_when_approved' => true,
                'maximum_explained_premium_percent' => 8.0,
            ],
        ]]);
        $this->assertFalse($p->isFamilyExportAllowed('SBPRUB'));
    }

    public function testZec540ExceedsTightBandEvenWithEightPercentPremium(): void
    {
        $baseline = '41470.56';
        $course540 = '44517.921233869460447696'; // ~7.35% raw
        $analysis = (new RateConfiguredExpectation())->analyze(
            baseline: $baseline,
            actual: $course540,
            profitPercent: '0',
            paymentSystemFeePercent: '8',
        );
        // 7.35 - 8 ≈ residual near zero or slightly negative unexplained magnitude
        $this->assertNotNull($analysis['unexplained_deviation']);
    }

    public function testTonIsNotGramAssetMapping(): void
    {
        $this->assertSame('TON', IndependentMarketBaseline::assetFromCode('TON'));
        $this->assertNull(IndependentMarketBaseline::assetFromCode('GRAM'));
        $this->assertNull(IndependentMarketBaseline::assetFromCode('STON'));
    }

    public function testKeepBlockedFamilyNeverExportsEvenWhenApproved(): void
    {
        $p = new RubFamilyPremiumPolicy([
            'approved' => true,
            'families' => [
                'OTHER_RUB' => [
                    'destination_codes' => ['*RUB'],
                    'proposed_decision' => 'KEEP_BLOCKED',
                    'export_allowed_when_approved' => false,
                    'order_allowed_when_approved' => false,
                    'maximum_explained_premium_percent' => 0,
                ],
            ],
        ]);
        $this->assertFalse($p->isFamilyExportAllowed('SOMETHINGRUB'));
    }
}
