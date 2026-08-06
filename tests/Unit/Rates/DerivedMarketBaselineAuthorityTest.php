<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\DerivedMarketBaselineAuthority;
use App\Services\Rates\IndependentMarketBaseline;
use PHPUnit\Framework\TestCase;

final class DerivedMarketBaselineAuthorityTest extends TestCase
{
    /** @var list<string> */
    private array $tempFilesToClean = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFilesToClean as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function writeConfig(array $directions): string
    {
        $path = sys_get_temp_dir() . '/derived-baseline-' . uniqid('', true) . '.json';
        $this->tempFilesToClean[] = $path;
        file_put_contents($path, json_encode([
            'version' => 1,
            'directions' => $directions,
        ]));

        return $path;
    }

    private function directionCfg(): array
    {
        return [
            'asset_leg' => ['symbol' => 'TONUSDT'],
            'fiat_leg' => ['symbol' => 'USDKZT'],
            'haircut' => '0.999',
            'freshness' => [
                'crypto_max_age_seconds' => 900,
                'fiat_max_age_seconds' => 21600,
            ],
            'ownership' => [
                'parser_source_name' => 'Ручной курс',
                'block_bestchange_overwrite' => true,
                'keep_bestchange_link_status' => 0,
            ],
        ];
    }

    public function test_valid_legs_produce_positive_rate(): void
    {
        $path = $this->writeConfig(['1249' => $this->directionCfg()]);
        $now = date('Y-m-d H:i:s');
        $baseline = new IndependentMarketBaseline([
            'TONUSDT' => ['rate' => '1.6', 'source' => 'fixture', 'as_of' => $now, 'age_seconds' => 10],
            'USDKZT' => ['rate' => '500', 'source' => 'fixture', 'as_of' => $now, 'age_seconds' => 20],
        ], allowDatabase: false);
        $auth = new DerivedMarketBaselineAuthority($baseline, $path);
        $eval = $auth->evaluate(1249);
        $this->assertTrue($eval['ok'], json_encode($eval));
        $this->assertSame('799.200000000000', $eval['rate']);
        $this->assertSame('DERIVED_MARKET_BASELINE', $eval['authority']);
    }

    public function test_zero_asset_leg_unavailable(): void
    {
        $path = $this->writeConfig(['1249' => $this->directionCfg()]);
        $now = date('Y-m-d H:i:s');
        $baseline = new IndependentMarketBaseline([
            'TONUSDT' => ['rate' => '0', 'source' => 'fixture', 'as_of' => $now, 'age_seconds' => 10],
            'USDKZT' => ['rate' => '500', 'source' => 'fixture', 'as_of' => $now, 'age_seconds' => 20],
        ], allowDatabase: false);
        $auth = new DerivedMarketBaselineAuthority($baseline, $path);
        $eval = $auth->evaluate(1249);
        $this->assertFalse($eval['ok']);
        $this->assertSame('asset_leg_missing_or_non_positive', $eval['reason']);
    }

    public function test_stale_fiat_leg_unavailable(): void
    {
        $path = $this->writeConfig(['1249' => $this->directionCfg()]);
        $fresh = date('Y-m-d H:i:s');
        $stale = date('Y-m-d H:i:s', time() - 99999);
        $baseline = new IndependentMarketBaseline([
            'TONUSDT' => ['rate' => '1.6', 'source' => 'fixture', 'as_of' => $fresh, 'age_seconds' => 10],
            'USDKZT' => ['rate' => '500', 'source' => 'fixture', 'as_of' => $stale, 'age_seconds' => 99999],
        ], allowDatabase: false);
        $auth = new DerivedMarketBaselineAuthority($baseline, $path);
        $eval = $auth->evaluate(1249);
        $this->assertFalse($eval['ok']);
        $this->assertSame('fiat_leg_stale', $eval['reason']);
    }

    public function test_missing_legs_unavailable(): void
    {
        $path = $this->writeConfig(['1249' => $this->directionCfg()]);
        $auth = new DerivedMarketBaselineAuthority(new IndependentMarketBaseline([], allowDatabase: false), $path);
        $eval = $auth->evaluate(1249);
        $this->assertFalse($eval['ok']);
        $this->assertNotNull($eval['reason']);
    }

    public function test_usdt_peg_uses_unity_asset_leg(): void
    {
        $cfg = $this->directionCfg();
        $cfg['asset_leg'] = ['symbol' => 'USDT_PEG'];
        $cfg['fiat_leg'] = ['symbol' => 'USDRUB'];
        $path = $this->writeConfig(['1599' => $cfg]);
        $now = date('Y-m-d H:i:s');
        $baseline = new IndependentMarketBaseline([
            'USDRUB' => ['rate' => '100', 'source' => 'fixture', 'as_of' => $now, 'age_seconds' => 5],
        ], allowDatabase: false);
        $auth = new DerivedMarketBaselineAuthority($baseline, $path);
        $eval = $auth->evaluate(1599);
        $this->assertTrue($eval['ok'], json_encode($eval));
        $this->assertSame('99.900000000000', $eval['rate']);
    }

    public function test_asset_from_code_maps_revbusd_and_gram(): void
    {
        $this->assertSame('USDT', IndependentMarketBaseline::assetFromCode('REVBUSD'));
        $this->assertSame('TON', IndependentMarketBaseline::assetFromCode('GRAM'));
        // DAI is not a mapped baseline asset; unsupported codes stay null.
        $this->assertNull(IndependentMarketBaseline::assetFromCode('DAI'));
    }
}
