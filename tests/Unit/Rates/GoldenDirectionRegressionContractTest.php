<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;

/**
 * Golden direction regression set — structural contracts for freeze directions.
 */
final class GoldenDirectionRegressionContractTest extends TestCase
{
    private function fixture(): array
    {
        $path = dirname(__DIR__, 3).'/tests/fixtures/rates/golden_directions_freeze.json';
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data);

        return $data;
    }

    public function test_golden_set_covers_required_ids(): void
    {
        $ids = array_map(static fn (array $d): int => (int) $d['id'], $this->fixture()['directions']);
        foreach ([1525, 1537, 1552, 1556, 1586, 3222, 16, 29, 34, 15, 27, 1415, 41, 410, 132] as $need) {
            $this->assertContains($need, $ids, "missing golden id $need");
        }
    }

    public function test_owners_cover_bestchange_zelle_and_derived_families(): void
    {
        $owners = array_unique(array_map(
            static fn (array $d): string => (string) $d['owner'],
            $this->fixture()['directions']
        ));
        sort($owners);
        $this->assertSame(['BESTCHANGE', 'ZELLE'], $owners);
    }

    public function test_bestchange_rows_pin_profit_storage_and_zero_floating(): void
    {
        foreach ($this->fixture()['directions'] as $row) {
            if (($row['owner'] ?? '') !== 'BESTCHANGE') {
                continue;
            }
            $this->assertSame('BestChange', $row['parser_source_name']);
            $this->assertSame('profit', $row['storage_field']);
            $this->assertSame('0', $row['floating_fee']);
            $this->assertSame('BestChange.position', $row['base_source']);
        }
    }

    public function test_zelle_rows_pin_benchmark_and_neg6_margin(): void
    {
        $zelleIds = [];
        foreach ($this->fixture()['directions'] as $row) {
            if (($row['owner'] ?? '') !== 'ZELLE') {
                continue;
            }
            $zelleIds[] = (int) $row['id'];
            $this->assertStringStartsWith('ZELLEUSD->USDT', $row['pair']);
            $this->assertSame('ZELLE_USDTTRC20_BENCHMARK', $row['parser_source_name']);
            $this->assertSame('-6', $row['floating_fee']);
            $this->assertSame('6', $row['display_profit']);
        }
        $this->assertSame([1525, 1537, 1552, 1556, 1586, 3222], $zelleIds);
    }

    public function test_invariants_catalog_complete(): void
    {
        $inv = $this->fixture()['invariants'];
        foreach (['bestchange', 'zelle', 'stored_course', 'xml', 'ownership', 'economics', 'derived_provider_integrity'] as $key) {
            $this->assertArrayHasKey($key, $inv);
            $this->assertNotEmpty($inv[$key]);
        }
    }

    public function test_commercial_resolver_source_matches_golden_storage_fields(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Rates/CommercialAdjustmentResolver.php');
        $this->assertStringContainsString("family(\$direction) === 'ZELLE'", $src);
        $this->assertStringContainsString('FIELD_FLOATING_FEE', $src);
        $this->assertStringContainsString('FIELD_PROFIT', $src);
    }
}
