<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\CanonicalDirectionEligibility;
use App\Services\Rates\RateAuthorityDriftMonitor;
use App\Services\Rates\RateDirectionEligibility;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Certification remediation: public catalog must not be weaker than quote,
 * and the authority-drift monitor must count PUBLIC_QUOTE_UNAVAILABLE.
 */
final class PublicQuoteUnavailableGateTest extends TestCase
{
    public function test_catalog_context_requires_quote_and_order(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Rates/CanonicalDirectionEligibility.php');
        $this->assertStringContainsString('CTX_CATALOG, self::CTX_SELECTOR => $quote && $order', $src);
    }

    public function test_courses_adjacency_uses_full_catalog_eligibility(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3).'/packages/Courses/Concerns/ConfiguresAttributes.php'
        );
        $this->assertStringContainsString('CanonicalDirectionEligibility::CTX_CATALOG', $src);
        $this->assertStringContainsString('catalogEligibility->evaluate', $src);
        $this->assertStringContainsString('never advertise a direction the quote/order surface rejects', $src);
        $this->assertStringContainsString("'is_type_rate'", $src);
    }

    public function test_monitor_alert_class_for_public_quote_unavailable(): void
    {
        $m = new RateAuthorityDriftMonitor();
        $fn = new ReflectionMethod($m, 'alertClassForRoot');
        $fn->setAccessible(true);
        $this->assertSame('PUBLIC_QUOTE_UNAVAILABLE', $fn->invoke($m, 'PUBLIC_QUOTE_UNAVAILABLE'));
        $this->assertSame('PUBLIC_QUOTE_DIVERGENCE', $fn->invoke($m, 'PUBLIC_QUOTE_RATE_MISMATCH'));
    }

    public function test_monitor_skips_gate_when_weak_prefilter_fails(): void
    {
        $m = new RateAuthorityDriftMonitor();
        $fn = new ReflectionMethod($m, 'applyPublicQuoteUnavailableGate');
        $fn->setAccessible(true);
        $eval = [
            'direction_id' => 1,
            'severity' => 'OK',
            'root_cause' => null,
            'public_impact' => false,
        ];
        // Non-positive course → weak prefilter fails → unchanged.
        $row = (object) ['id' => 1, 'course_value' => '0'];
        $out = $fn->invoke($m, $eval, $row);
        $this->assertSame('OK', $out['severity']);
        $this->assertNull($out['root_cause']);
        $this->assertArrayNotHasKey('public_quote_unavailable', $out);
    }

    public function test_weak_prefilter_helper_rejects_zero_course(): void
    {
        $this->assertFalse(CanonicalDirectionEligibility::passesPublicCatalogPrefilter(168, '0'));
        $this->assertFalse(CanonicalDirectionEligibility::passesPublicCatalogPrefilter(0, '1.0'));
        $this->assertTrue(CanonicalDirectionEligibility::passesPublicCatalogPrefilter(168, '30.72'));
    }

    public function test_temporarily_unavailable_error_code_stable(): void
    {
        $this->assertSame(
            'DIRECTION_TEMPORARILY_UNAVAILABLE',
            RateDirectionEligibility::ERROR_DIRECTION_TEMPORARILY_UNAVAILABLE
        );
    }
}
