<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;

/**
 * Wave 2A: NEO→CARDTJS (2237) must remain BestChange-owned and absent from derived registry.
 */
final class Wave2aBestChangeMigratedRegistryInvariantTest extends TestCase
{
    private function registryPath(): string
    {
        return dirname(__DIR__, 3).'/resources/rates/derived-market-baseline-directions.json';
    }

    private function manifestPath(): string
    {
        return dirname(__DIR__, 3).'/resources/rates/wave2a-bestchange-migrated-ids.json';
    }

    public function test_manifest_lists_2237_only(): void
    {
        $man = json_decode((string) file_get_contents($this->manifestPath()), true);
        $this->assertIsArray($man);
        $this->assertSame([2237], array_map('intval', $man['direction_ids'] ?? []));
        $this->assertSame('BestChange', $man['expected_owner'] ?? null);
    }

    public function test_2237_absent_from_derived_registry(): void
    {
        $data = json_decode((string) file_get_contents($this->registryPath()), true);
        $dirs = $data['directions'] ?? [];
        $this->assertArrayNotHasKey('2237', $dirs);
        $this->assertSame(1487, count($dirs));
    }

    public function test_poison_registry_with_2237_fails_sha_contract(): void
    {
        $expected = '525dc61e2403a480f04091f62016d567572132a149cceae34936f8c9b7e35955';
        $raw = file_get_contents($this->registryPath());
        $this->assertSame($expected, hash('sha256', $raw));

        $data = json_decode($raw, true);
        $data['directions']['2237'] = [
            'from' => 'NEO',
            'to' => 'CARDTJS',
            'ownership' => [
                'parser_source_name' => 'DERIVED_MARKET_BASELINE',
                'block_bestchange_overwrite' => true,
            ],
        ];
        $poison = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->assertNotSame($expected, hash('sha256', $poison));
    }
}
