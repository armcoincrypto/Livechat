<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\CanonicalDirectionEligibility;
use App\Services\Rates\CanonicalDirectionRateCalculator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Regression: CTX_CATALOG catalog adjacency projection must supply is_type_rate
 * so RubFamily commercial fee semantics match full-row eligibility.
 */
final class CatalogSelectProjectionContractTest extends TestCase
{
    public function test_required_projection_includes_is_type_rate(): void
    {
        $this->assertContains(
            'is_type_rate',
            CanonicalDirectionEligibility::CTX_CATALOG_REQUIRED_PROJECTION_FIELDS
        );
    }

    public function test_configures_attributes_select_includes_required_fields(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3).'/packages/Courses/Concerns/ConfiguresAttributes.php'
        );
        $start = strpos($src, '->select([');
        $this->assertNotFalse($start);
        $end = strpos($src, '])', $start);
        $this->assertNotFalse($end);
        $block = substr($src, $start, $end - $start);

        $missing = CanonicalDirectionEligibility::missingCatalogProjectionFields(
            $this->extractQuotedFields($block)
        );
        $this->assertSame(
            [],
            $missing,
            'ConfiguresAttributes catalog select missing: '.implode(', ', $missing)
        );
        $this->assertStringContainsString("'is_type_rate'", $block);
    }

    public function test_missing_catalog_projection_detects_absent_is_type_rate(): void
    {
        $without = [
            'id', 'id_currency1', 'id_currency2', 'status', 'deleted_at',
            'course_value', 'profit', 'floating_fee', 'fix_fee',
            'parser_source_name', 'allow_export', 'is_error_rate',
            'type_reserve', 'direction_reserve',
        ];
        $this->assertSame(
            ['is_type_rate'],
            CanonicalDirectionEligibility::missingCatalogProjectionFields($without)
        );
    }

    public function test_calculator_treats_missing_is_type_rate_as_disabled(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3).'/app/Services/Rates/CanonicalDirectionRateCalculator.php'
        );
        $this->assertStringContainsString(
            "(int) (\$direction->is_type_rate ?? 0) === 1",
            $src
        );
        // Documents the fail-closed default that makes projection completeness mandatory.
        $this->assertTrue(method_exists(CanonicalDirectionRateCalculator::class, 'calculate'));
    }

    public function test_public_quote_unavailable_gate_source_still_present(): void
    {
        // Preserve bad9792 monitor class wiring (no undo of prior remediation).
        $m = new \App\Services\Rates\RateAuthorityDriftMonitor();
        $fn = new ReflectionMethod($m, 'alertClassForRoot');
        $fn->setAccessible(true);
        $this->assertSame('PUBLIC_QUOTE_UNAVAILABLE', $fn->invoke($m, 'PUBLIC_QUOTE_UNAVAILABLE'));
    }

    /**
     * @return list<string>
     */
    private function extractQuotedFields(string $block): array
    {
        preg_match_all("/'([a-z0-9_]+)'/", $block, $m);

        return array_values(array_unique($m[1] ?? []));
    }
}
