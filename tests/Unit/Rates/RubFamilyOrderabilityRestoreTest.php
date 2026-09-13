<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\Currency;
use App\Models\DirectionExchange;
use App\Services\Rates\BestChangeMappingVerifier;
use App\Services\Rates\IndependentMarketBaseline;
use App\Services\Rates\RateConfiguredExpectation;
use App\Services\Rates\RateDirectionEligibility;
use App\Services\Rates\RateExportQuarantine;
use App\Services\Rates\RubFamilyPremiumPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Eligibility-only restore: valid BestChange-selected RUB rates stay orderable
 * unless they breach the family hard maximum or fail closed (no baseline).
 */
final class RubFamilyOrderabilityRestoreTest extends TestCase
{
    private function productionLikePolicy(): RubFamilyPremiumPolicy
    {
        return new RubFamilyPremiumPolicy([
            'approved' => true,
            'approved_by' => 'test',
            'default_thresholds' => [
                'unexplained_warning_percent' => 1.0,
                'unexplained_block_percent' => 2.5,
            ],
            'families' => [
                'SBERRUB' => $this->family(['SBERRUB'], 6.0, 6.0, 9.0),
                'TCSBRUB' => $this->family(['TCSBRUB', 'TBRUB'], 6.0, 6.0, 9.0),
                'SBPRUB' => $this->family(['SBPRUB'], 4.0, 6.0, 9.0),
                'ACRUB' => $this->family(['ACRUB'], 4.0, 6.0, 9.0),
                'YAMRUB' => $this->family(['YAMRUB'], 5.0, 6.0, 8.0),
                'RFBRUB' => $this->family(['RFBRUB'], 6.0, 6.0, 9.0),
            ],
        ]);
    }

    /**
     * @param list<string> $codes
     * @return array<string,mixed>
     */
    private function family(array $codes, float $target, float $warn, float $hard): array
    {
        return [
            'destination_codes' => $codes,
            'decision' => 'APPROVE',
            'target_premium_min_percent' => 1.5,
            'target_premium_max_percent' => $target,
            'warning_premium_percent' => $warn,
            'hard_maximum_premium_percent' => $hard,
            'export_allowed_when_approved' => true,
            'order_allowed_when_approved' => true,
        ];
    }

    public function testLegitimateMarketPremiumIsReviewAndOrderable(): void
    {
        $eval = $this->productionLikePolicy()->evaluateCoinRub('SBERRUB', 7.008, 1.0);
        $this->assertSame('REVIEW', $eval['classification']);
        $this->assertTrue($eval['order_allowed']);
        $this->assertTrue($eval['export_allowed']);
        $this->assertLessThan(9.0, 7.008);
    }

    public function testReviewBoundaryJustAboveWarningIsOrderable(): void
    {
        $eval = $this->productionLikePolicy()->evaluateCoinRub('SBERRUB', 6.0001, 1.0);
        $this->assertSame('REVIEW', $eval['classification']);
        $this->assertTrue($eval['order_allowed']);
    }

    public function testReviewBoundaryJustBelowHardMaxIsOrderable(): void
    {
        $eval = $this->productionLikePolicy()->evaluateCoinRub('SBERRUB', 8.999, 1.0);
        $this->assertSame('REVIEW', $eval['classification']);
        $this->assertTrue($eval['order_allowed']);
        $this->assertContains('unexplained_above_expected_block', $eval['reasons']);
    }

    public function testTrueHardMaxAnomalyQuarantines(): void
    {
        $eval = $this->productionLikePolicy()->evaluateCoinRub('SBERRUB', 9.0001, 1.0);
        $this->assertSame('QUARANTINE_REQUIRED', $eval['classification']);
        $this->assertFalse($eval['order_allowed']);
        $this->assertFalse($eval['export_allowed']);
        $this->assertContains('raw_premium_exceeds_hard_maximum', $eval['reasons']);
    }

    public function testStaleBaselineFailsClosed(): void
    {
        $eval = $this->productionLikePolicy()->evaluateCoinRub('SBERRUB', null, 1.0);
        $this->assertSame('NO_BASELINE', $eval['classification']);
        $this->assertFalse($eval['order_allowed']);
        $this->assertFalse($eval['export_allowed']);
    }

    public function testBadBestChangeInputFailsClosedOnEverySurface(): void
    {
        $result = $this->eligibility()->evaluateDirection(
            $this->direction('USDTTRC20', 'SBERRUB', '0', '1'),
        );

        $this->assertFalse($result['quote_allowed']);
        $this->assertFalse($result['order_allowed']);
        $this->assertFalse($result['export_allowed']);
    }

    public function testSberUsdtTrc20LiveShapeIsOrderable(): void
    {
        $eval = $this->productionLikePolicy()->evaluateCoinRub('SBERRUB', 7.008, 1.0, 84.24456, 'USDTTRC20');
        $this->assertSame('REVIEW', $eval['classification']);
        $this->assertTrue($eval['order_allowed']);
        $this->assertSame('SBERRUB', $eval['family_key']);
    }

    public function testTcsUsdtTrc20LiveShapeIsOrderable(): void
    {
        $eval = $this->productionLikePolicy()->evaluateCoinRub('TCSBRUB', 7.008, 1.0, 84.24456, 'USDTTRC20');
        $this->assertSame('REVIEW', $eval['classification']);
        $this->assertTrue($eval['order_allowed']);
        $this->assertSame('TCSBRUB', $eval['family_key']);
    }

    public function testYooMoneyInsideTargetPasses(): void
    {
        $eval = $this->productionLikePolicy()->evaluateCoinRub('YAMRUB', 2.579, 0.0, 84.24456, 'USDTTRC20');
        $this->assertSame('PASS_EXPLAINED_SPREAD', $eval['classification']);
        $this->assertTrue($eval['order_allowed']);
        $this->assertSame('YAMRUB', $eval['family_key']);
    }

    public function testYooMoneyReviewBandIsOrderableUnderHardEight(): void
    {
        $eval = $this->productionLikePolicy()->evaluateCoinRub('YAMRUB', 7.2, 0.0);
        $this->assertSame('REVIEW', $eval['classification']);
        $this->assertTrue($eval['order_allowed']);
        $yamHard = $this->productionLikePolicy()->evaluateCoinRub('YAMRUB', 8.1, 0.0);
        $this->assertSame('QUARANTINE_REQUIRED', $yamHard['classification']);
        $this->assertFalse($yamHard['order_allowed']);
    }

    public function testSbpStaleUnexplainedWithinHardMaxIsOrderable(): void
    {
        // Live BTC→SBPRUB: ~7.69% vs CBR. SBP explained ceiling is 4, so
        // unexplained ~3.69 > 2.5 — previously quarantined by CBR comparator.
        $eval = $this->productionLikePolicy()->evaluateCoinRub('SBPRUB', 7.694, 0.0);
        $this->assertSame('REVIEW', $eval['classification']);
        $this->assertTrue($eval['order_allowed']);
        $this->assertContains('unexplained_above_expected_block', $eval['reasons']);
    }

    public function testAlfaUsdterc20ReviewIsOrderable(): void
    {
        $eval = $this->productionLikePolicy()->evaluateCoinRub('ACRUB', 5.176, 0.0, 84.24456, 'USDTERC20');
        $this->assertSame('REVIEW', $eval['classification']);
        $this->assertTrue($eval['order_allowed']);
        $this->assertSame('ACRUB', $eval['family_key']);
    }

    public function testBtcEthHighPremiumUnderHardMaxIsOrderable(): void
    {
        $p = $this->productionLikePolicy();
        $btc = $p->evaluateCoinRub('SBERRUB', 7.910, 0.0, 6522248.41, 'BTC');
        $eth = $p->evaluateCoinRub('TCSBRUB', 8.025, 0.0, 211748.70, 'ETH');
        $this->assertSame('REVIEW', $btc['classification']);
        $this->assertSame('REVIEW', $eth['classification']);
        $this->assertTrue($btc['order_allowed']);
        $this->assertTrue($eth['order_allowed']);
    }

    public function testBtcEthAboveHardMaxStayQuarantined(): void
    {
        $p = $this->productionLikePolicy();
        $btc = $p->evaluateCoinRub('SBERRUB', 12.5, 0.0, 6522248.41, 'BTC');
        $eth = $p->evaluateCoinRub('SBERRUB', 15.0, 0.0, 211748.70, 'ETH');
        $this->assertSame('QUARANTINE_REQUIRED', $btc['classification']);
        $this->assertSame('QUARANTINE_REQUIRED', $eth['classification']);
        $this->assertFalse($btc['order_allowed']);
        $this->assertFalse($eth['order_allowed']);
        $this->assertContains('raw_premium_exceeds_hard_maximum', $btc['reasons']);
    }

    public function testReviewSurfaceGateDoesNotStripOrder(): void
    {
        $result = $this->eligibility()->evaluateDirection(
            $this->direction('USDTTRC20', 'SBERRUB', '90.1488', '1'),
        );

        $this->assertSame('REVIEW', $result['classification']);
        $this->assertTrue($result['quote_allowed']);
        $this->assertTrue($result['order_allowed']);
        $this->assertTrue($result['export_allowed']);
        $this->assertNotContains('rub_family_review_public_blocked', $result['reasons']);
    }

    private function eligibility(): RateDirectionEligibility
    {
        $dir = sys_get_temp_dir() . '/rub_orderability_mapping_' . getmypid();
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir . '/currencies.json', json_encode([
            ['id' => 2, 'name' => '[USDTTRC20] - Tether TRC20'],
            ['id' => 3, 'name' => '[SBERRUB] - Sber RUB'],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($dir . '/codes.json', json_encode([
            '2' => ['id' => '2', 'code' => 'USDTTRC20', 'name' => '[USDTTRC20] - Tether TRC20'],
            '3' => ['id' => '3', 'code' => 'SBERRUB', 'name' => '[SBERRUB] - Sber RUB'],
        ], JSON_THROW_ON_ERROR));

        return new RateDirectionEligibility(
            quarantine: new RateExportQuarantine(),
            mappingVerifier: new BestChangeMappingVerifier(
                $dir . '/currencies.json',
                $dir . '/codes.json',
            ),
            rubPolicy: $this->productionLikePolicy(),
            baseline: new IndependentMarketBaseline([
                'USDRUB' => [
                    'rate' => '84.24456',
                    'source' => 'test-cbr',
                    'as_of' => gmdate('c'),
                ],
            ], false),
            expectation: new RateConfiguredExpectation(),
        );
    }

    private function direction(string $from, string $to, string $course, string $profit = '0'): DirectionExchange
    {
        $direction = new DirectionExchange();
        $direction->forceFill([
            'id' => 15,
            'id_currency1' => 101,
            'id_currency2' => 102,
            'status' => 1,
            'allow_export' => 0,
            'course_value' => $course,
            'profit' => $profit,
            'parser_source_name' => 'BestChange',
            'type_reserve' => 1,
            'direction_reserve' => '1000000',
        ]);
        $direction->setRelation('currency1', (new Currency())->forceFill([
            'id' => 101,
            'designation_xml' => $from,
            'status' => 0,
        ]));
        $direction->setRelation('currency2', (new Currency())->forceFill([
            'id' => 102,
            'designation_xml' => $to,
            'status' => 0,
        ]));

        return $direction;
    }
}
