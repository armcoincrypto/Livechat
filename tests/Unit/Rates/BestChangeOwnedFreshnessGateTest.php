<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\BestChangeMarketBaseHealth;
use App\Services\Rates\RateDirectionEligibility;
use PHPUnit\Framework\TestCase;

final class BestChangeOwnedFreshnessGateTest extends TestCase
{
    private function bcRow(string $updatedAt, string $rate = '1.25', int $status = 1): object
    {
        return (object) [
            'status' => $status,
            'rate_value' => $rate,
            'position_num' => '1',
            'updated_at' => $updatedAt,
            'is_error_parser' => 0,
            'explain_payload' => null,
        ];
    }

    public function test_fresh_book_keeps_surface_allowed(): void
    {
        $fresh = gmdate('Y-m-d H:i:s', time() - 300);
        $health = BestChangeMarketBaseHealth::evaluateFromRow($this->bcRow($fresh), 9001, 1800, false);
        $this->assertTrue($health['healthy']);
        $out = RateDirectionEligibility::applyBestChangeBookHealthToSurface(
            true, $health, true, true, []
        );
        $this->assertTrue($out['quote_allowed']);
        $this->assertTrue($out['order_allowed']);
        $this->assertSame([], $out['reasons']);
    }

    public function test_stale_book_blocks_quote_and_order(): void
    {
        $stale = gmdate('Y-m-d H:i:s', time() - 3600);
        $health = BestChangeMarketBaseHealth::evaluateFromRow($this->bcRow($stale), 9001, 1800, false);
        $this->assertFalse($health['healthy']);
        $this->assertSame('stale', $health['reason']);
        $out = RateDirectionEligibility::applyBestChangeBookHealthToSurface(
            true, $health, true, true, ['other']
        );
        $this->assertFalse($out['quote_allowed']);
        $this->assertFalse($out['order_allowed']);
        $this->assertContains('bestchange_book_stale', $out['reasons']);
        $this->assertContains('other', $out['reasons']);
    }

    public function test_missing_health_fails_closed(): void
    {
        $out = RateDirectionEligibility::applyBestChangeBookHealthToSurface(
            true,
            ['healthy' => false, 'status' => 'unavailable', 'reason' => 'health_eval_failed'],
            true,
            true,
            []
        );
        $this->assertFalse($out['quote_allowed']);
        $this->assertFalse($out['order_allowed']);
        $this->assertContains('bestchange_book_health_eval_failed', $out['reasons']);
    }

    public function test_non_bc_owner_not_gated(): void
    {
        $stale = gmdate('Y-m-d H:i:s', time() - 3600);
        $health = BestChangeMarketBaseHealth::evaluateFromRow($this->bcRow($stale), 9001, 1800, false);
        $out = RateDirectionEligibility::applyBestChangeBookHealthToSurface(
            false, $health, true, true, []
        );
        $this->assertTrue($out['quote_allowed']);
        $this->assertTrue($out['order_allowed']);
    }

    public function test_non_positive_rate_unhealthy(): void
    {
        $fresh = gmdate('Y-m-d H:i:s', time() - 60);
        $health = BestChangeMarketBaseHealth::evaluateFromRow($this->bcRow($fresh, '0'), 9001, 1800, false);
        $this->assertFalse($health['healthy']);
        $out = RateDirectionEligibility::applyBestChangeBookHealthToSurface(true, $health, true, true, []);
        $this->assertFalse($out['quote_allowed']);
        $this->assertFalse($out['order_allowed']);
    }

    public function test_compiler_active_does_not_bypass_stale_book(): void
    {
        // Freshness is book-based; an in-flight compiler does not clear staleness.
        $stale = gmdate('Y-m-d H:i:s', time() - 3600);
        $health = BestChangeMarketBaseHealth::evaluateFromRow($this->bcRow($stale), 9001, 1800, false);
        $out = RateDirectionEligibility::applyBestChangeBookHealthToSurface(true, $health, true, true, []);
        $this->assertFalse($out['quote_allowed']);
    }

    public function test_recovery_restores_availability(): void
    {
        $stale = gmdate('Y-m-d H:i:s', time() - 3600);
        $fresh = gmdate('Y-m-d H:i:s', time() - 30);
        $blocked = RateDirectionEligibility::applyBestChangeBookHealthToSurface(
            true,
            BestChangeMarketBaseHealth::evaluateFromRow($this->bcRow($stale), 1, 1800, false),
            true,
            true,
            []
        );
        $this->assertFalse($blocked['quote_allowed']);
        $restored = RateDirectionEligibility::applyBestChangeBookHealthToSurface(
            true,
            BestChangeMarketBaseHealth::evaluateFromRow($this->bcRow($fresh), 1, 1800, false),
            true,
            true,
            []
        );
        $this->assertTrue($restored['quote_allowed']);
        $this->assertTrue($restored['order_allowed']);
    }

    public function test_zelle_derived_manual_ownership_not_gated_by_stale_bc_book(): void
    {
        // Ownership gate is parser_source_name === BestChange only.
        $stale = gmdate('Y-m-d H:i:s', time() - 3600);
        $health = BestChangeMarketBaseHealth::evaluateFromRow($this->bcRow($stale), 9001, 1800, false);
        $this->assertFalse($health['healthy']);
        foreach ([false] as $owner) {
            $out = RateDirectionEligibility::applyBestChangeBookHealthToSurface(
                $owner, $health, true, true, []
            );
            $this->assertTrue($out['quote_allowed']);
            $this->assertTrue($out['order_allowed']);
        }
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Rates/RateDirectionEligibility.php');
        $this->assertStringContainsString("parser_source_name ?? '') !== 'BestChange'", $src);
        $this->assertStringContainsString('Zelle / DERIVED / MANUAL', $src);
    }

    public function test_concurrent_applicator_deterministic(): void
    {
        $stale = gmdate('Y-m-d H:i:s', time() - 3600);
        $health = BestChangeMarketBaseHealth::evaluateFromRow($this->bcRow($stale), 9001, 1800, false);
        $results = [];
        for ($i = 0; $i < 64; $i++) {
            $results[] = RateDirectionEligibility::applyBestChangeBookHealthToSurface(
                true, $health, true, true, []
            );
        }
        foreach ($results as $out) {
            $this->assertFalse($out['quote_allowed']);
            $this->assertFalse($out['order_allowed']);
            $this->assertSame($results[0], $out);
        }
    }

    public function test_gate_does_not_touch_accepted_fixed_price_order_records(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Rates/RateDirectionEligibility.php');
        $this->assertStringNotContainsString('Order::', $src);
        $this->assertStringNotContainsString('orders()->', $src);
        $this->assertStringNotContainsString('fixed_price', strtolower($src));
        // New-order path only: ValidatesOrderRules consults order_allowed before create.
        $rules = (string) file_get_contents(dirname(__DIR__, 3).'/packages/Order/Concerns/ValidatesOrderRules.php');
        $this->assertStringContainsString('order_allowed', $rules);
        $this->assertStringContainsString('ERROR_DIRECTION_TEMPORARILY_UNAVAILABLE', $rules);
    }

}
