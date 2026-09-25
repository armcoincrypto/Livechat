<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\BestChangeCurrencyCatalogGuard;
use PHPUnit\Framework\TestCase;

/**
 * Migration certification must not trust a stale local bestchange-codes.json alone.
 */
final class BestChangeCatalogCriticalCodesTest extends TestCase
{
    private function criticalPath(): string
    {
        return dirname(__DIR__, 3).'/resources/rates/bestchange-catalog-critical-codes.json';
    }

    public function test_critical_codes_include_wave2d_rails(): void
    {
        $cfg = json_decode((string) file_get_contents($this->criticalPath()), true);
        $this->assertIsArray($cfg);
        $codes = $cfg['critical_codes'] ?? [];
        $this->assertSame('PAYTMINR', $codes['292'] ?? null);
        $this->assertSame('UPIINR', $codes['327'] ?? null);
        $this->assertSame('ZELLEUSD', $codes['308'] ?? null);
        $this->assertSame('CASHUSD', $codes['89'] ?? null);
        $this->assertSame('TCSBRUB', $codes['105'] ?? null);
        $this->assertSame('CACHE_AND_AUDIT', $cfg['source_class'] ?? null);
    }

    public function test_guard_detects_stale_local_code_vs_live_catalog(): void
    {
        $dir = sys_get_temp_dir().'/bc_catalog_crit_'.bin2hex(random_bytes(4));
        mkdir($dir);
        // Stale local codes: missing 292 / wrong 108 already covered elsewhere.
        file_put_contents($dir.'/bestchange-codes.json', json_encode([
            '10' => ['id' => '10', 'code' => 'USDTTRC20', 'name' => 'Tether TRC20'],
        ]));

        $liveRows = [
            ['id' => 292, 'code' => 'PAYTMINR', 'name' => 'Paytm INR'],
            ['id' => 10, 'code' => 'USDTTRC20', 'name' => 'Tether TRC20 (USDT)'],
        ];
        $guard = BestChangeCurrencyCatalogGuard::fromCatalogRows($liveRows, $dir.'/bestchange-codes.json');

        $okKnown = $guard->validateId(10, 'USDTTRC20');
        $this->assertTrue($okKnown['ok']);

        $missingLocalButLiveOk = $guard->validateId(292, 'PAYTMINR');
        // Catalog (live) matches expected code even if local codes.json lacks the id.
        $this->assertTrue($missingLocalButLiveOk['ok'], json_encode($missingLocalButLiveOk));

        $mismatch = $guard->validateId(292, 'PRUSD');
        $this->assertFalse($mismatch['ok']);
        $this->assertSame('currency_mapping_mismatch', $mismatch['reason']);
    }
}
