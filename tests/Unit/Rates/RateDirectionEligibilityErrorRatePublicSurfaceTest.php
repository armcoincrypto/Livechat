<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\RateDirectionEligibility;
use PHPUnit\Framework\TestCase;

/**
 * is_error_rate=1 means retained/broken rate — must not be quoteable, orderable, or XML-exported.
 */
final class RateDirectionEligibilityErrorRatePublicSurfaceTest extends TestCase
{
    public function test_explain_blocks_quote_order_export_when_error_rate(): void
    {
        $out = RateDirectionEligibility::make()->explain([
            'id' => 2629,
            'status' => 1,
            'allow_export' => 0,
            'is_error_rate' => 1,
            'course_value' => '1.954044',
            'profit' => '0',
            'deleted_at' => null,
            'currency1_status' => 0,
            'currency2_status' => 0,
            'from' => 'NEO',
            'to' => 'CASHUSD',
            'reserve_ok' => true,
            'require_verified_export_mapping' => false,
            'require_independent_baseline' => false,
        ]);

        $this->assertFalse($out['eligible_for_quote']);
        $this->assertFalse($out['eligible_for_order']);
        $this->assertFalse($out['eligible_for_export']);
        $this->assertContains('is_error_rate_blocks_public_surface', $out['reasons']);
    }

    public function test_explain_allows_healthy_active_without_error_rate(): void
    {
        $out = RateDirectionEligibility::make()->explain([
            'id' => 1525,
            'status' => 1,
            'allow_export' => 0,
            'is_error_rate' => 0,
            'course_value' => '1.0',
            'profit' => '0',
            'deleted_at' => null,
            'currency1_status' => 0,
            'currency2_status' => 0,
            'from' => 'ZELLEUSD',
            'to' => 'USDTTRC20',
            'reserve_ok' => true,
            'require_verified_export_mapping' => false,
            'require_independent_baseline' => false,
        ]);

        $this->assertTrue($out['eligible_for_quote']);
        $this->assertTrue($out['eligible_for_order']);
        $this->assertTrue($out['eligible_for_export']);
        $this->assertNotContains('is_error_rate_blocks_public_surface', $out['reasons']);
    }

    public function test_source_no_longer_blesses_retained_website_sales(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Rates/RateDirectionEligibility.php');
        $this->assertStringNotContainsString('Quote/order may still use retained', $src);
        $this->assertStringContainsString('is_error_rate_blocks_public_surface', $src);
        $this->assertStringContainsString('$quoteAllowed = false;', $src);
        $this->assertStringContainsString('$orderAllowed = false;', $src);
    }
}
