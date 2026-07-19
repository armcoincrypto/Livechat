<?php
declare(strict_types=1);
namespace Tests\Unit\Rates;
use App\Services\Rates\IndependentMarketBaseline;
use App\Services\Rates\PeerRateSelector;
use PHPUnit\Framework\TestCase;
final class RateCanonicalizationTest extends TestCase
{
    public function testProviderMedianSelection(): void
    {
        $sel = new PeerRateSelector();
        $r = $sel->selectValidPeerRate(['100','101','99'], preferred: null, minSample: 3);
        $this->assertTrue($r['ok']);
        $this->assertNotNull($r['median']);
    }
    public function testProviderDisagreementKeepsMedian(): void
    {
        $base = new IndependentMarketBaseline([
            'BTCUSDT' => ['rate' => '64000', 'source' => 'a', 'as_of' => gmdate('c')],
        ], allowDatabase: false);
        $q = $base->quote('BTCUSDT');
        $this->assertNotNull($q);
        $this->assertEqualsWithDelta(64000.0, (float)$q['rate'], 0.01);
    }
    public function testCatalogDriftFixtureStillDetected(): void
    {
        $dir = sys_get_temp_dir().'/bc_drift_'.getmypid();
        @mkdir($dir.'/bestchange', 0777, true);
        file_put_contents($dir.'/bestchange/currencies.json', json_encode([['id'=>108,'name'=>'[CARDVND] - VND']]));
        file_put_contents($dir.'/bestchange-codes.json', json_encode(['108'=>['id'=>'108','code'=>'PRUSD','name'=>'Payeer USD']]));
        $g = new \App\Services\Rates\BestChangeCurrencyCatalogGuard($dir.'/bestchange/currencies.json', $dir.'/bestchange-codes.json');
        $r = $g->validateId(108, 'PRUSD');
        $this->assertFalse($r['ok']);
    }
}
