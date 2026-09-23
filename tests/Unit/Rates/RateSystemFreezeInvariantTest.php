<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;

/**
 * Machine-tested rate-system freeze invariants (source + fixture contracts).
 * Does not mutate live economics.
 */
final class RateSystemFreezeInvariantTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function fixture(): array
    {
        $path = $this->root().'/tests/fixtures/rates/golden_directions_freeze.json';
        $this->assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data);
        $this->assertSame(1, $data['schemaVersion'] ?? null);

        return $data;
    }

    public function test_freeze_config_is_active(): void
    {
        $cfg = include $this->root().'/config/rate_system_freeze.php';
        $this->assertSame('ACTIVE', $cfg['RATE_SYSTEM_FREEZE']);
        $this->assertCount(6, $cfg['zelle_usdt_direction_ids']);
        $this->assertSame('-6', $cfg['expected_zelle_floating_fee']);
    }

    public function test_bestchange_invariants_are_encoded_in_services(): void
    {
        $topn = (string) file_get_contents($this->root().'/app/Services/Rates/BestChangeExternalTopNController.php');
        $this->assertMatchesRegularExpression('/profit|floor|protect|never/i', $topn);

        $resolver = (string) file_get_contents($this->root().'/app/Services/Rates/CommercialAdjustmentResolver.php');
        $this->assertStringContainsString('BestChange → direction_exchange.profit', $resolver);
        $this->assertStringContainsString('do not invent floating_fee', $resolver);

        $safety = (string) file_get_contents($this->root().'/app/Services/Rates/AdminPricingOperatorSafety.php');
        $this->assertTrue(
            str_contains($safety, 'OWNERSHIP_SWITCH_REQUIRES_DELIBERATE_ACTION')
            || str_contains($safety, 'deliberate')
        );
        $this->assertStringContainsString('OWNER', $safety);
        $this->assertStringContainsString('EFFECTIVE_POSITION', $safety);
        $this->assertStringContainsString('PROFIT', $safety);
    }

    public function test_zelle_invariants_map_pribyl_to_floating_fee(): void
    {
        $resolver = (string) file_get_contents($this->root().'/app/Services/Rates/CommercialAdjustmentResolver.php');
        $this->assertStringContainsString('ZELLE_USDTTRC20_BENCHMARK → direction_exchange.floating_fee', $resolver);
        $this->assertStringContainsString('FIELD_FLOATING_FEE', $resolver);

        $zelle = (string) file_get_contents($this->root().'/app/Services/Rates/ZelleUsdUsdtBenchmarkAuthority.php');
        $this->assertNotSame('', $zelle);

        $fixture = $this->fixture();
        $zelleRows = array_values(array_filter(
            $fixture['directions'],
            static fn (array $d): bool => ($d['owner'] ?? '') === 'ZELLE'
        ));
        $this->assertCount(6, $zelleRows);
        foreach ($zelleRows as $row) {
            $this->assertSame('-6', $row['floating_fee']);
            $this->assertSame('-3', $row['fix_fee']);
            $this->assertSame('0', $row['profit']);
            $this->assertSame('floating_fee', $row['storage_field']);
            $this->assertSame('0.94', $row['expected_floating_multiplier']);
        }
    }

    public function test_xml_online_never_empty_invariants_present(): void
    {
        $gate = (string) file_get_contents($this->root().'/app/Services/Rates/RatesXmlMonitorGate.php');
        $this->assertStringContainsString('mustKeepFullPublicFeed', $gate);
        $this->assertStringContainsString('forceEmptyPolicy', $gate);
        $this->assertStringContainsString('EMPTY_RATES_XML', $gate);

        $pub = (string) file_get_contents($this->root().'/app/Services/Rates/AtomicPublicXmlPublisher.php');
        $this->assertMatchesRegularExpression('/last.?good|reject|empty/i', $pub);

        $health = (string) file_get_contents($this->root().'/app/Services/Rates/RatesXmlPublicFeedHealth.php');
        $this->assertStringContainsString('assertOnlineInvariant', $health);
        $this->assertStringContainsString('SITE_ONLINE_EMPTY_OR_177B_XML', $health);
    }

    public function test_stored_course_reassert_and_topn_guards_present(): void
    {
        $reassert = (string) file_get_contents($this->root().'/app/Services/Rates/BestChangeStoredCourseReassert.php');
        $this->assertNotSame('', $reassert);
        $this->assertMatchesRegularExpression('/stale|manual_rate|reassert/i', $reassert);

        $export = (string) file_get_contents($this->root().'/packages/Courses/Export/ExportCourses.php');
        $this->assertStringContainsString('mustKeepFullPublicFeed', $export);
    }

    public function test_financial_audit_logger_is_append_only_jsonl_with_retention(): void
    {
        $src = (string) file_get_contents($this->root().'/app/Services/Rates/AdminPricingFinancialAuditLogger.php');
        $this->assertStringContainsString('File::append', $src);
        $this->assertStringContainsString('admin-pricing-audit', $src);
        $this->assertStringContainsString('MAX_FILES', $src);
        $this->assertStringContainsString('operator', $src);
        $this->assertStringContainsString('old', $src);
        $this->assertStringContainsString('new', $src);
        $this->assertStringContainsString('direction_id', $src);
    }

    public function test_predeploy_gate_and_lock_status_scripts_exist(): void
    {
        $this->assertFileExists($this->root().'/scripts/ops/rate_system_predeploy_gate.sh');
        $this->assertFileExists($this->root().'/scripts/ops/rate_system_lock_status.sh');
        $gate = (string) file_get_contents($this->root().'/scripts/ops/rate_system_predeploy_gate.sh');
        foreach ([
            'PUBLIC_QUOTE_DEFECT_TOTAL',
            'ORDER_ECONOMICS_DEFECT_TOTAL',
            'TOPN_DEFECT_TOTAL',
            'STORED_COURSE_DEFECT_TOTAL',
            'POSITION_SELECTOR_DEFECT_TOTAL',
            'MAPPING_DEFECT_TOTAL',
            'ZELLE',
            'empty_rates_match',
            'RATE_SYSTEM_PREDEPLOY_GATE',
        ] as $needle) {
            $this->assertStringContainsString($needle, $gate);
        }
    }

    public function test_freeze_policy_docs_exist(): void
    {
        foreach ([
            'docs/pricing/PRICING_ARCHITECTURE.md',
            'docs/pricing/ZELLE_PRICING_MODEL.md',
            'docs/pricing/XML_FEED_INVARIANTS.md',
            'docs/ops/RATE_SYSTEM_RUNBOOK.md',
            'docs/release/RATE_SYSTEM_FREEZE_POLICY.md',
        ] as $rel) {
            $this->assertFileExists($this->root().'/'.$rel, $rel);
        }
        $policy = (string) file_get_contents($this->root().'/docs/release/RATE_SYSTEM_FREEZE_POLICY.md');
        $this->assertStringContainsString('RATE_SYSTEM_FREEZE=ACTIVE', $policy);
        $this->assertStringContainsString('RELEASE_COORDINATOR', $policy);
        $this->assertStringContainsString('FEATURE_AGENT', $policy);
    }
}
