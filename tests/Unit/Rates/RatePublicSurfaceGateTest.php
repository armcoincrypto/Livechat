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
 * Regression for DEF-003: one canonical surface decision.
 * Uses policy evaluateCoinRub mapping without requiring live DB.
 */
final class RatePublicSurfaceGateTest extends TestCase
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
                'SBERRUB' => [
                    'destination_codes' => ['SBERRUB'],
                    'decision' => 'APPROVE',
                    'target_premium_min_percent' => 3.0,
                    'target_premium_max_percent' => 5.0,
                    'warning_premium_percent' => 6.0,
                    'hard_maximum_premium_percent' => 8.0,
                    'export_allowed_when_approved' => true,
                    'order_allowed_when_approved' => true,
                ],
            ],
        ]);
    }

    public function testDirection268PatternBlocksOrderAndExport(): void
    {
        $eval = $this->approvedPolicy()->evaluateCoinRub('SBPRUB', 7.143137, 0.3);
        $this->assertSame('QUARANTINE_REQUIRED', $eval['classification']);
        $this->assertFalse($eval['order_allowed']);
        $this->assertFalse($eval['export_allowed']);
    }

    public function testReviewBlocksOrderAndExportAllowsQuoteSemantics(): void
    {
        $eval = $this->approvedPolicy()->evaluateCoinRub('SBPRUB', 6.2, 0.0);
        $this->assertSame('REVIEW', $eval['classification']);
        $this->assertFalse($eval['order_allowed']);
        $this->assertFalse($eval['export_allowed']);
    }

    public function testPassExplainedAllowsAllPublicSurfaces(): void
    {
        $eval = $this->approvedPolicy()->evaluateCoinRub('SBPRUB', 4.0, 0.0);
        $this->assertSame('PASS_EXPLAINED_SPREAD', $eval['classification']);
        $this->assertTrue($eval['order_allowed']);
        $this->assertTrue($eval['export_allowed']);
    }

    public function testErrorCodeConstantStable(): void
    {
        $this->assertSame(
            'DIRECTION_TEMPORARILY_UNAVAILABLE',
            RateDirectionEligibility::ERROR_DIRECTION_TEMPORARILY_UNAVAILABLE
        );
    }

    public function testUnknownRubSourceCannotBypassCanonicalEligibility(): void
    {
        $result = $this->eligibility(
            baseline: new IndependentMarketBaseline([], false),
        )->evaluateDirection($this->direction('DASH', 'SBPRUB', '104'));

        $this->assertSame('NO_BASELINE', $result['classification']);
        $this->assertSame('NO_BASELINE', $result['baseline_status']);
        $this->assertFalse($result['quote_allowed']);
        $this->assertFalse($result['order_allowed']);
        $this->assertFalse($result['export_allowed']);
        $this->assertFalse($result['BestChange_allowed']);
        $this->assertContains('no_independent_baseline', $result['blocking_reasons']);
    }

    public function testKnownRubSourceWithoutBaselineBlocksExportAndBestChange(): void
    {
        $result = $this->eligibility(
            baseline: new IndependentMarketBaseline([], false),
        )->evaluateDirection($this->direction('USDTTRC20', 'SBPRUB', '104'));

        $this->assertSame('NO_BASELINE', $result['classification']);
        $this->assertFalse($result['export_allowed']);
        $this->assertFalse($result['BestChange_allowed']);
    }

    public function testKnownCertifiedRubSourceStillPassesEverySurface(): void
    {
        $result = $this->eligibility(
            baseline: new IndependentMarketBaseline([
                'USDRUB' => [
                    'rate' => '100',
                    'source' => 'test-usd-rub',
                    'as_of' => gmdate('c'),
                ],
            ], false),
        )->evaluateDirection($this->direction('USDTTRC20', 'SBPRUB', '104'));

        $this->assertSame('PASS_EXPLAINED_SPREAD', $result['classification']);
        $this->assertTrue($result['quote_allowed']);
        $this->assertTrue($result['order_allowed']);
        $this->assertTrue($result['export_allowed']);
        $this->assertTrue($result['BestChange_allowed']);
    }

    private function eligibility(IndependentMarketBaseline $baseline): RateDirectionEligibility
    {
        $dir = sys_get_temp_dir() . '/rate_surface_mapping_' . getmypid();
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir . '/currencies.json', json_encode([
            ['id' => 1, 'name' => '[DASH] - Dash'],
            ['id' => 2, 'name' => '[USDTTRC20] - Tether TRC20'],
            ['id' => 3, 'name' => '[SBPRUB] - SBP RUB'],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($dir . '/codes.json', json_encode([
            '1' => ['id' => '1', 'code' => 'DASH', 'name' => '[DASH] - Dash'],
            '2' => ['id' => '2', 'code' => 'USDTTRC20', 'name' => '[USDTTRC20] - Tether TRC20'],
            '3' => ['id' => '3', 'code' => 'SBPRUB', 'name' => '[SBPRUB] - SBP RUB'],
        ], JSON_THROW_ON_ERROR));

        return new RateDirectionEligibility(
            quarantine: new RateExportQuarantine(),
            mappingVerifier: new BestChangeMappingVerifier(
                $dir . '/currencies.json',
                $dir . '/codes.json',
            ),
            rubPolicy: $this->approvedPolicy(),
            baseline: $baseline,
            expectation: new RateConfiguredExpectation(),
        );
    }

    private function direction(string $from, string $to, string $course): DirectionExchange
    {
        $direction = new DirectionExchange();
        $direction->forceFill([
            'id' => 9001,
            'id_currency1' => 101,
            'id_currency2' => 102,
            'status' => 1,
            'allow_export' => 0,
            'course_value' => $course,
            'profit' => '0',
            'parser_source_name' => 'independent-test',
            'type_reserve' => 1,
            'direction_reserve' => '1000000',
        ]);
        $direction->setRelation('currency1', (new Currency())->forceFill([
            'id' => 101,
            'designation_xml' => $from,
        ]));
        $direction->setRelation('currency2', (new Currency())->forceFill([
            'id' => 102,
            'designation_xml' => $to,
        ]));

        return $direction;
    }
}
