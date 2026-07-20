<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\RateDirectionEligibility;
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
}
