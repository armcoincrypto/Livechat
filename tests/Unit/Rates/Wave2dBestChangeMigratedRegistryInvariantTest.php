<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;

/**
 * Wave 2D: 2870 ETC→PAYTMINR and 1482 USDCERC20→CASHUSD must remain BestChange-owned
 * and absent from derived registry.
 */
final class Wave2dBestChangeMigratedRegistryInvariantTest extends TestCase
{
    private function registryPath(): string
    {
        return dirname(__DIR__, 3).'/resources/rates/derived-market-baseline-directions.json';
    }

    private function manifestPath(): string
    {
        return dirname(__DIR__, 3).'/resources/rates/wave2d-bestchange-migrated-ids.json';
    }

    public function test_manifest_lists_2870_and_1482(): void
    {
        $man = json_decode((string) file_get_contents($this->manifestPath()), true);
        $this->assertIsArray($man);
        $this->assertSame([2870, 1482], array_map('intval', $man['direction_ids'] ?? []));
        $this->assertSame('BestChange', $man['expected_owner'] ?? null);
        $this->assertSame(292, (int) ($man['rails']['2870']['bc_out'] ?? 0));
        $this->assertSame(89, (int) ($man['rails']['1482']['bc_out'] ?? 0));
        $this->assertSame(19, (int) ($man['rails']['1482']['city_id'] ?? 0));
    }

    public function test_migrated_ids_absent_from_derived_registry(): void
    {
        $data = json_decode((string) file_get_contents($this->registryPath()), true);
        $dirs = $data['directions'] ?? [];
        $this->assertArrayNotHasKey('2870', $dirs);
        $this->assertArrayNotHasKey('1482', $dirs);
        $this->assertArrayNotHasKey('2237', $dirs);
        $this->assertArrayHasKey('1513', $dirs);
        $this->assertSame(1485, count($dirs));
    }

    public function test_poison_registry_with_1482_fails_sha_contract(): void
    {
        $expected = '9280c847fba5f4ea52c41ce248394d0289d2d68f32a4df2e53142388ec4acd20';
        $raw = file_get_contents($this->registryPath());
        $this->assertSame($expected, hash('sha256', $raw));

        $data = json_decode($raw, true);
        $data['directions']['1482'] = [
            'from' => 'USDCERC20',
            'to' => 'CASHUSD',
            'ownership' => [
                'parser_source_name' => 'DERIVED_MARKET_BASELINE',
                'block_bestchange_overwrite' => true,
            ],
        ];
        $poison = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->assertNotSame($expected, hash('sha256', $poison));
    }
}
