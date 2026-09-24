<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\DerivedMarketBaselineAuthority;
use App\Services\Rates\IndependentMarketBaseline;
use App\Services\Rates\ProviderIntegrity\DerivedLegLastKnownGoodStore;
use App\Services\Rates\ProviderIntegrity\ExchangeSymbolStatusRegistry;
use App\Services\Rates\ProviderIntegrity\ProviderLegAggregator;
use App\Services\Rates\ProviderIntegrity\ProviderQuoteClassifier;
use PHPUnit\Framework\TestCase;

/**
 * Provider integrity regressions for DERIVED / IMB legs (GRAM→BTC incident class).
 */
final class ProviderIntegrityRegressionTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $p) {
            @unlink($p);
        }
        parent::tearDown();
    }

    private function tmpLkg(): DerivedLegLastKnownGoodStore
    {
        $path = sys_get_temp_dir() . '/lkg-' . uniqid('', true) . '.json';
        $this->tempFiles[] = $path;

        return new DerivedLegLastKnownGoodStore($path);
    }

    private function registryBreakTon(): ExchangeSymbolStatusRegistry
    {
        return new ExchangeSymbolStatusRegistry([
            'binance' => ['TONUSDT' => 'BREAK'],
        ]);
    }

    public function test_a_break_provider_excluded(): void
    {
        $clf = new ProviderQuoteClassifier($this->registryBreakTon());
        $r = $clf->classify([
            'code' => '[binance_ton-usdt]',
            'code_in' => 'TON',
            'code_out' => 'USDT',
            'summa' => '1.60',
            'status' => 1,
            'age_seconds' => 10,
        ], 900);
        $this->assertFalse($r['eligible']);
        $this->assertSame(ProviderQuoteClassifier::BREAK, $r['class']);
    }

    public function test_b_delisted_provider_excluded(): void
    {
        $clf = new ProviderQuoteClassifier(new ExchangeSymbolStatusRegistry([
            'binance' => ['XMRUSDT' => 'DELISTED'],
        ]));
        $r = $clf->classify([
            'code' => '[binance_xmr-usdt]',
            'code_in' => 'XMR',
            'code_out' => 'USDT',
            'summa' => '100',
            'status' => 1,
            'age_seconds' => 5,
        ], 900);
        $this->assertFalse($r['eligible']);
        $this->assertSame(ProviderQuoteClassifier::DELISTED, $r['class']);
    }

    public function test_c_stale_ticker_excluded(): void
    {
        $clf = new ProviderQuoteClassifier();
        $r = $clf->classify([
            'code' => '[gate_btc-usdt]',
            'code_in' => 'BTC',
            'code_out' => 'USDT',
            'summa' => '80000',
            'status' => 1,
            'age_seconds' => 5000,
        ], 900);
        $this->assertFalse($r['eligible']);
        $this->assertSame(ProviderQuoteClassifier::STALE, $r['class']);
    }

    public function test_d_no_book_no_kline_rejected(): void
    {
        $clf = new ProviderQuoteClassifier();
        $r = $clf->classify([
            'code' => '[okx_eth-usdt]',
            'code_in' => 'ETH',
            'code_out' => 'USDT',
            'summa' => '3000',
            'status' => 1,
            'age_seconds' => 10,
            'book_available' => false,
            'kline_available' => false,
        ], 900);
        $this->assertFalse($r['eligible']);
        $this->assertSame(ProviderQuoteClassifier::NO_BOOK, $r['class']);
    }

    public function test_e_one_bad_two_healthy_median(): void
    {
        $agg = new ProviderLegAggregator(
            classifier: new ProviderQuoteClassifier($this->registryBreakTon()),
        );
        $out = $agg->aggregate([
            [
                'code' => '[binance_ton-usdt]',
                'summa' => '1.60',
                'status' => 1,
                'age_seconds' => 5,
                'as_of' => date('Y-m-d H:i:s'),
                'code_in' => 'TON',
                'code_out' => 'USDT',
            ],
            [
                'code' => '[kucoin_ton-usdt]',
                'summa' => '1.40',
                'status' => 1,
                'age_seconds' => 5,
                'as_of' => date('Y-m-d H:i:s'),
                'code_in' => 'TON',
                'code_out' => 'USDT',
            ],
            [
                'code' => '[gate_ton-usdt]',
                'summa' => '1.42',
                'status' => 1,
                'age_seconds' => 5,
                'as_of' => date('Y-m-d H:i:s'),
                'code_in' => 'TON',
                'code_out' => 'USDT',
            ],
        ], 900);
        $this->assertTrue($out['ok']);
        $this->assertSame(ProviderLegAggregator::QUALITY_HEALTHY, $out['quality_state']);
        $this->assertSame(2, $out['provider_count']);
        $this->assertEqualsWithDelta(1.41, (float) $out['rate'], 0.02);
        $classes = array_column($out['excluded'], 'class');
        $this->assertContains(ProviderQuoteClassifier::BREAK, $classes);
    }

    public function test_f_one_healthy_provider_degraded(): void
    {
        $agg = new ProviderLegAggregator(
            classifier: new ProviderQuoteClassifier($this->registryBreakTon()),
        );
        $out = $agg->aggregate([
            [
                'code' => '[binance_ton-usdt]',
                'summa' => '1.60',
                'status' => 1,
                'age_seconds' => 5,
                'as_of' => date('Y-m-d H:i:s'),
                'code_in' => 'TON',
                'code_out' => 'USDT',
            ],
            [
                'code' => '[kucoin_ton-usdt]',
                'summa' => '1.41',
                'status' => 1,
                'age_seconds' => 5,
                'as_of' => date('Y-m-d H:i:s'),
                'code_in' => 'TON',
                'code_out' => 'USDT',
            ],
        ], 900);
        $this->assertTrue($out['ok']);
        $this->assertSame(ProviderLegAggregator::QUALITY_DEGRADED_SINGLE, $out['quality_state']);
        $this->assertEqualsWithDelta(1.41, (float) $out['rate'], 0.001);
    }

    public function test_g_zero_healthy_quarantine_then_lkg(): void
    {
        $lkg = $this->tmpLkg();
        $lkg->put('TONUSDT', [
            'rate' => '1.41',
            'as_of' => gmdate('c'),
            'quality_state' => ProviderLegAggregator::QUALITY_HEALTHY,
            'source' => 'prior',
            'distinct_providers' => ['kucoin'],
        ]);
        $agg = new ProviderLegAggregator(
            classifier: new ProviderQuoteClassifier($this->registryBreakTon()),
        );
        $out = $agg->aggregate([
            [
                'code' => '[binance_ton-usdt]',
                'summa' => '1.60',
                'status' => 1,
                'age_seconds' => 5,
                'as_of' => date('Y-m-d H:i:s'),
                'code_in' => 'TON',
                'code_out' => 'USDT',
            ],
        ], 900);
        $this->assertFalse($out['ok']);
        $this->assertSame(ProviderLegAggregator::QUALITY_QUARANTINED, $out['quality_state']);

        $fresh = $lkg->getFresh('TONUSDT', 3600);
        $this->assertNotNull($fresh);
        $this->assertEqualsWithDelta(1.41, (float) $fresh['rate'], 0.001);

        $stalePath = sys_get_temp_dir() . '/lkg-stale-' . uniqid('', true) . '.json';
        $this->tempFiles[] = $stalePath;
        file_put_contents($stalePath, json_encode([
            'TONUSDT' => [
                'LAST_GOOD_PRICE' => '1.41',
                'LAST_GOOD_AT' => gmdate('c', time() - 10000),
                'SOURCE_SET' => ['kucoin'],
                'QUALITY_STATE' => 'HEALTHY',
                'source' => 'prior',
                'updated_at_unix' => time() - 10000,
            ],
        ]));
        $staleStore = new DerivedLegLastKnownGoodStore($stalePath);
        $this->assertNull($staleStore->getFresh('TONUSDT', 3600));
    }

    public function test_h_extreme_outlier_rejected(): void
    {
        $agg = new ProviderLegAggregator();
        $out = $agg->aggregate([
            [
                'code' => '[a_ton-usdt]',
                'summa' => '1.40',
                'status' => 1,
                'age_seconds' => 5,
                'as_of' => date('Y-m-d H:i:s'),
                'code_in' => 'TON',
                'code_out' => 'USDT',
            ],
            [
                'code' => '[b_ton-usdt]',
                'summa' => '1.41',
                'status' => 1,
                'age_seconds' => 5,
                'as_of' => date('Y-m-d H:i:s'),
                'code_in' => 'TON',
                'code_out' => 'USDT',
            ],
            [
                'code' => '[c_ton-usdt]',
                'summa' => '14.0',
                'status' => 1,
                'age_seconds' => 5,
                'as_of' => date('Y-m-d H:i:s'),
                'code_in' => 'TON',
                'code_out' => 'USDT',
            ],
        ], 900);
        $this->assertTrue($out['ok']);
        $this->assertSame(2, $out['provider_count']);
        $this->assertEqualsWithDelta(1.405, (float) $out['rate'], 0.01);
        $this->assertContains(ProviderQuoteClassifier::OUTLIER, array_column($out['excluded'], 'class'));
    }

    public function test_i_gram_btc_410_recomputes_from_healthy_ton(): void
    {
        $path = sys_get_temp_dir() . '/derived-410-' . uniqid('', true) . '.json';
        $this->tempFiles[] = $path;
        file_put_contents($path, json_encode([
            'directions' => [
                '410' => [
                    'from' => 'GRAM',
                    'to' => 'BTC',
                    'asset_leg' => ['symbol' => 'TONUSDT', 'orientation' => 'asset_per_usdt'],
                    'fiat_leg' => ['symbol' => 'BTCUSDT', 'orientation' => 'inverse_usdt'],
                    'haircut' => '0.999',
                    'freshness' => [
                        'crypto_max_age_seconds' => 900,
                        'fiat_max_age_seconds' => 21600,
                    ],
                ],
            ],
        ]));
        $now = date('Y-m-d H:i:s');
        $imb = new IndependentMarketBaseline([
            'TONUSDT' => [
                'rate' => '1.4065',
                'source' => 'certified_fallback:test',
                'as_of' => $now,
                'quality_state' => ProviderLegAggregator::QUALITY_HEALTHY,
            ],
            'BTCUSDT' => ['rate' => '83286', 'source' => 'binance', 'as_of' => $now],
        ], allowDatabase: false);
        $auth = new DerivedMarketBaselineAuthority($imb, $path);
        $ev = $auth->evaluate(410);
        $this->assertTrue($ev['ok'], json_encode($ev));
        $expected = 1.4065 * (1 / 83286) * 0.999;
        $this->assertEqualsWithDelta($expected, (float) $ev['rate'], 1e-12);
        // Poisoned Binance 1.60 path would be ~13% higher:
        $poisoned = 1.60 * (1 / 83286) * 0.999;
        $this->assertLessThan(0.95, (float) $ev['rate'] / $poisoned);
    }

    public function test_j_duplicate_break_rows_do_not_false_pass_as_healthy_median(): void
    {
        $agg = new ProviderLegAggregator(
            classifier: new ProviderQuoteClassifier($this->registryBreakTon()),
        );
        // Seven identical BREAK binance rows — historical false "healthy" median.
        $rows = [];
        for ($i = 0; $i < 7; $i++) {
            $rows[] = [
                'code' => '[binance_ton-usdt]',
                'summa' => '1.60000000',
                'status' => 1,
                'age_seconds' => 5,
                'as_of' => date('Y-m-d H:i:s'),
                'code_in' => 'TON',
                'code_out' => 'USDT',
            ];
        }
        $out = $agg->aggregate($rows, 900);
        $this->assertFalse($out['ok']);
        $this->assertSame(ProviderLegAggregator::QUALITY_QUARANTINED, $out['quality_state']);
        $this->assertSame(0, $out['provider_count']);
    }

    public function test_dedupe_identical_provider_codes_single_vote(): void
    {
        $agg = new ProviderLegAggregator();
        $out = $agg->aggregate([
            [
                'code' => '[kucoin_ton-usdt]',
                'summa' => '1.40',
                'status' => 1,
                'age_seconds' => 5,
                'as_of' => date('Y-m-d H:i:s'),
                'code_in' => 'TON',
                'code_out' => 'USDT',
            ],
            [
                'code' => '[kucoin_ton-usdt]',
                'summa' => '1.40',
                'status' => 1,
                'age_seconds' => 6,
                'as_of' => date('Y-m-d H:i:s'),
                'code_in' => 'TON',
                'code_out' => 'USDT',
            ],
            [
                'code' => '[gate_ton-usdt]',
                'summa' => '1.42',
                'status' => 1,
                'age_seconds' => 5,
                'as_of' => date('Y-m-d H:i:s'),
                'code_in' => 'TON',
                'code_out' => 'USDT',
            ],
        ], 900);
        $this->assertTrue($out['ok']);
        $this->assertSame(2, $out['provider_count']);
        $this->assertSame(ProviderLegAggregator::QUALITY_HEALTHY, $out['quality_state']);
    }
}
