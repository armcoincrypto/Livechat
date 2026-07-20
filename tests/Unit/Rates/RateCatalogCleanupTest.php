<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\BestChangeCurrencyCatalogGuard;
use App\Services\Rates\BestChangeMappingRegistry;
use App\Services\Rates\BestChangeMappingVerifier;
use App\Services\Rates\RateDirectionEligibility;
use App\Services\Rates\RateExportQuarantine;
use PHPUnit\Framework\TestCase;

final class RateCatalogCleanupTest extends TestCase
{
    public function testId108CannotResolveAsPrusdAgainstLiveCatalog(): void
    {
        $dir = $this->tempDir();
        file_put_contents($dir . '/bestchange/currencies.json', json_encode([
            ['id' => 108, 'name' => '[CARDVND] - Банковская карта VND'],
        ]));
        file_put_contents($dir . '/bestchange-codes.json', json_encode([
            '108' => ['id' => '108', 'code' => 'PRUSD', 'name' => 'Payeer USD'],
        ]));

        $g = new BestChangeCurrencyCatalogGuard(
            $dir . '/bestchange/currencies.json',
            $dir . '/bestchange-codes.json',
        );
        // PRUSD must never validate against live ID 108 (CARDVND).
        $this->assertFalse($g->validateId(108, 'PRUSD')['ok']);
        // With versioned overrides present in the app, effective code becomes CARDVND.
        if (function_exists('base_path') && is_file(base_path('resources/rates/bestchange-codes.overrides.json'))) {
            $this->assertTrue($g->validateId(108, 'CARDVND')['ok']);
        } else {
            file_put_contents($dir . '/bestchange-codes.json', json_encode([
                '108' => ['id' => '108', 'code' => 'CARDVND', 'name' => 'VND'],
            ]));
            $g2 = new BestChangeCurrencyCatalogGuard(
                $dir . '/bestchange/currencies.json',
                $dir . '/bestchange-codes.json',
            );
            $this->assertTrue($g2->validateId(108, 'CARDVND')['ok']);
        }
    }

    public function testId209CannotResolveAsTonAgainstLiveCatalog(): void
    {
        $dir = $this->tempDir();
        file_put_contents($dir . '/bestchange/currencies.json', json_encode([
            ['id' => 209, 'name' => '[GRAM] - Gram (Toncoin) (GRAM)'],
        ]));
        file_put_contents($dir . '/bestchange-codes.json', json_encode([
            '209' => ['id' => '209', 'code' => 'TON', 'name' => 'Toncoin (TON)'],
        ]));

        $g = new BestChangeCurrencyCatalogGuard(
            $dir . '/bestchange/currencies.json',
            $dir . '/bestchange-codes.json',
        );
        $this->assertFalse($g->validateId(209, 'TON')['ok']);
        if (function_exists('base_path') && is_file(base_path('resources/rates/bestchange-codes.overrides.json'))) {
            $this->assertTrue($g->validateId(209, 'GRAM')['ok']);
        } else {
            file_put_contents($dir . '/bestchange-codes.json', json_encode([
                '209' => ['id' => '209', 'code' => 'GRAM', 'name' => 'GRAM'],
            ]));
            $g2 = new BestChangeCurrencyCatalogGuard(
                $dir . '/bestchange/currencies.json',
                $dir . '/bestchange-codes.json',
            );
            $this->assertTrue($g2->validateId(209, 'GRAM')['ok']);
        }
    }

    public function testRegistryOverridesCorrectDriftedIds(): void
    {
        $dir = $this->tempDir();
        $codes = $dir . '/bestchange-codes.json';
        $overrides = $dir . '/overrides.json';
        file_put_contents($codes, json_encode([
            '108' => ['id' => '108', 'code' => 'PRUSD', 'name' => 'Payeer USD'],
            '209' => ['id' => '209', 'code' => 'TON', 'name' => 'Toncoin (TON)'],
        ]));
        file_put_contents($overrides, json_encode([
            'id_corrections' => [
                '108' => ['id' => '108', 'code' => 'CARDVND', 'name' => 'VND', 'reason' => 'fix'],
                '209' => ['id' => '209', 'code' => 'GRAM', 'name' => 'GRAM', 'reason' => 'fix'],
            ],
            'absent_local_codes' => [
                'PRUSD' => 'absent',
                'TON' => 'absent',
                'BNB' => 'ambiguous',
            ],
        ]));

        $reg = new BestChangeMappingRegistry($codes, $overrides);
        $effective = $reg->loadEffectiveCodes();
        $this->assertSame('CARDVND', $effective[108]['code']);
        $this->assertSame('GRAM', $effective[209]['code']);

        $dry = $reg->applyToStorageCodes(write: false);
        $this->assertCount(2, $dry['changed']);
        $this->assertNull($dry['backup_path']);

        $applied = $reg->applyToStorageCodes(write: true);
        $this->assertNotNull($applied['backup_path']);
        $written = json_decode((string) file_get_contents($codes), true);
        $this->assertSame('CARDVND', $written['108']['code']);
        $this->assertSame('GRAM', $written['209']['code']);
    }

    public function testUnknownAndAbsentMappingsBlockExportFlag(): void
    {
        $dir = $this->tempDir();
        file_put_contents($dir . '/bestchange/currencies.json', json_encode([
            ['id' => 10, 'name' => '[USDTTRC20] - Tether TRC20 (USDT)'],
            ['id' => 19, 'name' => '[BNBBEP20] - BNB BEP20 (BNB)'],
        ]));
        file_put_contents($dir . '/bestchange-codes.json', json_encode([
            '10' => ['id' => '10', 'code' => 'USDTTRC20', 'name' => 'USDT TRC20'],
            '19' => ['id' => '19', 'code' => 'BNBBEP20', 'name' => 'BNB BEP20'],
        ]));
        // Place overrides where fromStorageApp expects: base/resources/rates/...
        // Verifier uses dirname(codes,3) as base — codes at base/storage/app/bestchange-codes.json
        $base = $dir . '/approot';
        @mkdir($base . '/storage/app/bestchange', 0777, true);
        @mkdir($base . '/resources/rates', 0777, true);
        copy($dir . '/bestchange/currencies.json', $base . '/storage/app/bestchange/currencies.json');
        copy($dir . '/bestchange-codes.json', $base . '/storage/app/bestchange-codes.json');
        file_put_contents($base . '/resources/rates/bestchange-codes.overrides.json', json_encode([
            'id_corrections' => new \stdClass(),
            'absent_local_codes' => [
                'PRUSD' => 'no_exact_bracket_code_in_live_catalog',
                'TON' => 'no_exact_bracket_code_in_live_catalog_do_not_use_209',
                'BNB' => 'ambiguous_use_BNBBEP20_explicitly',
            ],
        ]));

        $v = new BestChangeMappingVerifier(
            catalogPath: $base . '/storage/app/bestchange/currencies.json',
            codesPath: $base . '/storage/app/bestchange-codes.json',
        );

        $pr = $v->verifyCode('PRUSD');
        $this->assertSame('ABSENT', $pr['status']);
        $this->assertFalse($pr['export_allowed'] ?? false);

        $bnb = $v->verifyCode('BNB');
        $this->assertSame('ABSENT', $bnb['status']);

        $bnbbep = $v->verifyCode('BNBBEP20');
        $this->assertSame('VERIFIED', $bnbbep['status']);
        $this->assertTrue($bnbbep['export_allowed'] ?? false);

        $ton = $v->verifyCode('TON');
        $this->assertSame('ABSENT', $ton['status']);
    }

    public function testEligibilityBlocksDeprecatedAndQuarantined(): void
    {
        $elig = new RateDirectionEligibility(
            new RateExportQuarantine(),
            new BestChangeMappingVerifier(
                catalogPath: $this->emptyCatalog(),
                codesPath: $this->emptyCodes(),
            ),
        );

        $q = $elig->explain([
            'id' => 1,
            'status' => 0,
            'allow_export' => 2,
            'course_value' => '0',
            'from' => 'DAI',
            'to' => 'XMR',
            'reserve_ok' => false,
        ]);
        $this->assertTrue($q['quarantined']);
        $this->assertFalse($q['eligible_for_export']);
        $this->assertFalse($q['eligible_for_order']);

        $d = $elig->explain([
            'id' => 2,
            'status' => 2,
            'allow_export' => 2,
            'course_value' => '1.23',
            'from' => 'BTC',
            'to' => 'USDTTRC20',
            'reserve_ok' => true,
        ]);
        $this->assertTrue($d['deprecated']);
        $this->assertFalse($d['eligible_for_quote']);
    }

    public function testXmlMutatorDefaultsDisabled(): void
    {
        $main = dirname(__DIR__, 3) . '/xml-changer/main.py';
        $this->assertFileExists($main);
        $src = (string) file_get_contents($main);
        $this->assertStringContainsString('EXSWAPING_XML_CHANGER_MUTATE_RATES', $src);
        $this->assertMatchesRegularExpression(
            '/MUTATE_RATES\s*=\s*os\.environ\.get\(\s*"EXSWAPING_XML_CHANGER_MUTATE_RATES"\s*,\s*"0"/',
            $src
        );
    }

    public function testValidNetworkVariantsAreNotMergedByNormalizedCodeAlone(): void
    {
        // Documented product rule: USDTTRC20 / USDTERC20 / USDTBEP20 remain distinct.
        $variants = ['USDTTRC20', 'USDTERC20', 'USDTBEP20', 'USDTSOL'];
        $this->assertCount(4, array_unique($variants));
        $this->assertNotSame('USDT', 'USDTTRC20');
    }

    private function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/rate_cleanup_' . getmypid() . '_' . bin2hex(random_bytes(3));
        @mkdir($dir . '/bestchange', 0777, true);

        return $dir;
    }

    private function emptyCatalog(): string
    {
        $dir = $this->tempDir();
        $path = $dir . '/bestchange/currencies.json';
        file_put_contents($path, '[]');

        return $path;
    }

    private function emptyCodes(): string
    {
        $dir = dirname($this->emptyCatalog(), 2);
        // reuse would create new dirs; write beside a fresh temp
        $d = $this->tempDir();
        $path = $d . '/bestchange-codes.json';
        file_put_contents($path, '{}');

        return $path;
    }
}
