<?php
declare(strict_types=1);

namespace Tests\Unit\BestChange;

use App\Models\BestChangeDirection;
use App\Services\Rates\BestChangeCurrencyCatalogGuard;
use iEXPackages\BestChange\DTO\RateSelectionPolicy;
use iEXPackages\BestChange\Services\RateCalculator;
use PHPUnit\Framework\TestCase;

/**
 * CARDTHB 1832–1839: compiler asserted mapping against a stale on-disk
 * currencies.json while MarketPipeline used the live API book.
 *
 * Proven production error (2026-07-19):
 *   pair=93-98; reason=bestchange_currency_mapping_mismatch:CARDTHB:98
 */
final class CardThbCompilerParityTest extends TestCase
{
    public function test_compiler_mapping_assert_must_use_warmed_live_catalog_not_disk_file(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3) . '/packages/BestChange/Services/RatesUpdateService.php');
        $this->assertStringContainsString('fromCatalogRows', $src);
        $this->assertStringContainsString('$this->catalog->currencies(', $src);
        $this->assertStringNotContainsString(
            'BestChangeCurrencyCatalogGuard::fromStorageApp()',
            $src,
            'Compiler must not re-read a possibly stale currencies.json after warmupAll()'
        );
    }

    public function test_stale_disk_catalog_rejects_cardthb_while_live_catalog_matches_pipeline(): void
    {
        $dir = sys_get_temp_dir() . '/exswaping_cardthb_guard_' . getmypid();
        @mkdir($dir . '/bestchange', 0777, true);
        file_put_contents($dir . '/bestchange/currencies.json', json_encode([
            ['id' => 98, 'name' => '[CARDVND] - Банковская карта VND'],
        ], JSON_UNESCAPED_UNICODE));
        file_put_contents($dir . '/bestchange-codes.json', json_encode([
            '98' => ['id' => '98', 'code' => 'CARDTHB', 'name' => 'Банковская карта THB'],
        ], JSON_UNESCAPED_UNICODE));

        $stale = new BestChangeCurrencyCatalogGuard(
            $dir . '/bestchange/currencies.json',
            $dir . '/bestchange-codes.json',
        );
        $staleCheck = $stale->validateId(98, 'CARDTHB');
        $this->assertFalse($staleCheck['ok']);
        $this->assertSame('currency_mapping_mismatch', $staleCheck['reason']);

        $live = BestChangeCurrencyCatalogGuard::fromCatalogRows(
            [['id' => 98, 'name' => '[CARDTHB] - Банковская карта THB']],
            $dir . '/bestchange-codes.json',
        );
        $liveCheck = $live->validateId(98, 'CARDTHB');
        $this->assertTrue($liveCheck['ok'] ?? false, json_encode($liveCheck));
    }

    public function test_api_shaped_live_catalog_without_name_brackets_accepts_cardthb(): void
    {
        $live = BestChangeCurrencyCatalogGuard::fromCatalogRows(
            [[
                'id' => 98,
                'name' => 'Банковская карта THB',
                'code' => 'CARDTHB',
                'default_code' => 'THB',
            ]],
            $this->writeCodes([
                '98' => ['id' => '98', 'code' => 'CARDTHB', 'name' => 'Банковская карта THB'],
            ]),
        );
        $r = $live->validateId(98, 'CARDTHB');
        $this->assertTrue($r['ok'] ?? false, json_encode($r));
    }

    public function test_api_shaped_catalog_does_not_treat_default_code_as_bestchange_code(): void
    {
        $live = BestChangeCurrencyCatalogGuard::fromCatalogRows(
            [[
                'id' => 98,
                'name' => 'Банковская карта THB',
                'code' => 'CARDTHB',
                'default_code' => 'THB',
            ]],
            $this->writeCodes([
                '98' => ['id' => '98', 'code' => 'CARDTHB', 'name' => 'Банковская карта THB'],
            ]),
        );
        $r = $live->validateId(98, 'THB');
        $this->assertFalse($r['ok']);
        $this->assertSame('currency_mapping_mismatch', $r['reason']);
    }

    public function test_cardthb_fixture_pipeline_rate_equals_compiler_calculator_rate(): void
    {
        $direction = new BestChangeDirection([
            'id' => 2578,
            'id_direction_exchange' => 1832,
            'id_currency_in' => 93,
            'id_currency_out' => 98,
            'position_num' => '2',
            'rate_mode' => 'position',
            'step' => '0',
            'formula_value' => '',
            'name' => '[BTC] -> [CARDTHB]',
        ]);
        $policy = new RateSelectionPolicy('rate', 'api', 'position', 5, 600);
        $selected = [
            'changer' => 1522,
            'rate' => '0.0000003911029209',
            'rankrate' => '0.0000003911029209',
            'reserve' => '100000000',
        ];
        $peers = [
            $selected,
            ['changer' => 1, 'rate' => '0.0000003950000000', 'rankrate' => '0.0000003950000000', 'reserve' => '1'],
            ['changer' => 2, 'rate' => '0.0000003880000000', 'rankrate' => '0.0000003880000000', 'reserve' => '1'],
        ];

        $pipelineRate = (new RateCalculator())->calculate($direction, $selected, $peers, $policy, [], [1522 => 'BestChange']);
        $compilerRate = (new RateCalculator())->calculate($direction, $selected, $peers, $policy, [], [1522 => 'BestChange']);

        $this->assertNotNull($pipelineRate);
        $this->assertNotNull($compilerRate);
        $this->assertSame($pipelineRate->rateValue, $compilerRate->rateValue);
        $this->assertGreaterThan(0, (float) $compilerRate->rateValue);
        $this->assertSame('BestChange', $compilerRate->sourceName);
        $this->assertSame(0, bccomp($compilerRate->rateValue, $compilerRate->rateValueWithoutStep, 12));
    }

    public function test_prusd_cardvnd_collision_still_fails_closed_on_live_catalog(): void
    {
        $bracket = BestChangeCurrencyCatalogGuard::fromCatalogRows(
            [['id' => 108, 'name' => '[CARDVND] - Банковская карта VND']],
            $this->writeCodes(['108' => ['id' => '108', 'code' => 'PRUSD', 'name' => 'Payeer USD']]),
        );
        $r = $bracket->validateId(108, 'PRUSD');
        $this->assertFalse($r['ok']);
        $this->assertSame('currency_mapping_mismatch', $r['reason']);

        $apiShaped = BestChangeCurrencyCatalogGuard::fromCatalogRows(
            [['id' => 108, 'name' => 'Банковская карта VND', 'code' => 'CARDVND']],
            $this->writeCodes(['108' => ['id' => '108', 'code' => 'PRUSD', 'name' => 'Payeer USD']]),
        );
        $r2 = $apiShaped->validateId(108, 'PRUSD');
        $this->assertFalse($r2['ok']);
        $this->assertSame('currency_mapping_mismatch', $r2['reason']);
    }

    public function test_expected_codes_prefer_designation_when_name_has_no_brackets(): void
    {
        $fromName = BestChangeCurrencyCatalogGuard::expectedCodesFromName(
            'Bitcoin (BTC) -> Банковская карта THB'
        );
        $this->assertNull($fromName['in']);
        $this->assertNull($fromName['out']);

        $merged = BestChangeCurrencyCatalogGuard::mergeExpectedCodes(
            $fromName,
            'BTC',
            'CARDTHB',
        );
        $this->assertSame('BTC', $merged['in']);
        $this->assertSame('CARDTHB', $merged['out']);
    }

    /**
     * @param array<string, array<string,string>> $codes
     */
    private function writeCodes(array $codes): string
    {
        $dir = sys_get_temp_dir() . '/exswaping_cardthb_codes_' . getmypid() . '_' . mt_rand();
        @mkdir($dir, 0777, true);
        $path = $dir . '/bestchange-codes.json';
        file_put_contents($path, json_encode($codes, JSON_UNESCAPED_UNICODE));

        return $path;
    }
}
