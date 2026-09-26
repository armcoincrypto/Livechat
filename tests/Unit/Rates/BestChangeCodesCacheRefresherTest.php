<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\BestChangeCodesCacheRefresher;
use iEXPackages\BestChange\Services\BestChangeCatalogRepository;
use PHPUnit\Framework\TestCase;

final class BestChangeCodesCacheRefresherTest extends TestCase
{
    public function test_refresh_merges_live_codes_and_preserves_overrides(): void
    {
        $dir = sys_get_temp_dir().'/bc_codes_refresh_'.bin2hex(random_bytes(4));
        mkdir($dir);
        $codesPath = $dir.'/bestchange-codes.json';
        $overridesPath = $dir.'/overrides.json';

        file_put_contents($codesPath, json_encode([
            '10' => ['id' => '10', 'code' => 'USDTTRC20', 'name' => 'old'],
        ]));
        file_put_contents($overridesPath, json_encode([
            'id_corrections' => [
                '108' => [
                    'id' => '108',
                    'code' => 'CARDVND',
                    'name' => 'Bank card VND',
                    'reason' => 'intentional',
                ],
            ],
        ]));

        $catalog = $this->createMock(BestChangeCatalogRepository::class);
        $catalog->expects($this->once())->method('warmupAll')->with(true);
        $catalog->method('currencies')->willReturn([
            ['id' => 292, 'code' => 'PAYTMINR', 'name' => 'Paytm INR'],
            ['id' => 327, 'code' => 'UPIINR', 'name' => 'UPI INR'],
            ['id' => 308, 'code' => 'ZELLEUSD', 'name' => 'Zelle USD'],
            ['id' => 10, 'code' => 'USDTTRC20', 'name' => 'Tether TRC20'],
            // Must be skipped even if live somehow returned it
            ['id' => 999, 'code' => 'PRUSD', 'name' => 'fake'],
        ]);

        $refresher = new BestChangeCodesCacheRefresher($catalog, $codesPath, $overridesPath);
        $result = $refresher->refresh(write: true, forceCatalogWarmup: true);

        $this->assertTrue($result['written']);
        $after = json_decode((string) file_get_contents($codesPath), true);
        $this->assertSame('PAYTMINR', $after['292']['code']);
        $this->assertSame('UPIINR', $after['327']['code']);
        $this->assertSame('ZELLEUSD', $after['308']['code']);
        $this->assertSame('CARDVND', $after['108']['code']);
        $this->assertArrayNotHasKey('999', $after);
        $this->assertContains('108:CARDVND', $result['overrides_applied']);
    }
}
