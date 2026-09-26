<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;

final class KeepDerivedCityLiquidityBlockTest extends TestCase
{
    public function test_1513_auto_migration_blocked(): void
    {
        $path = dirname(__DIR__, 3).'/resources/rates/keep-derived-city-liquidity-block.json';
        $cfg = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($cfg);
        $d = $cfg['directions']['1513'] ?? null;
        $this->assertIsArray($d);
        $this->assertTrue($d['auto_migration_blocked']);
        $this->assertSame('DERIVED_MARKET_BASELINE', $d['owner_required']);
        $this->assertSame(0, $d['bc_status_required']);
        $this->assertSame(89, $d['bc_in']);
        $this->assertSame(105, $d['bc_out']);
        $this->assertSame(0, $d['city_id']);
        $this->assertSame('CITY/LIQUIDITY_BLOCK', $d['class']);

        $pending = json_decode(
            (string) file_get_contents(dirname(__DIR__, 3).'/resources/rates/wave2d-mapping-repair-pending.json'),
            true
        );
        $this->assertTrue($pending['repairs']['1513']['auto_migration_blocked']);
    }
}
