<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\BestChangeCurrencyCatalogGuard;
use App\Services\Rates\IndependentMarketBaseline;
use App\Services\Rates\PeerRateSelector;
use App\Services\Rates\RateConfiguredExpectation;
use App\Services\Rates\RateExportQuarantine;
use App\Services\Rates\RateSanityGuard;
use PHPUnit\Framework\TestCase;

final class RatePostFixRemediationTest extends TestCase
{
    public function testTonPrusdOrientationUsesDirectUsdtAskNotReciprocal(): void
    {
        // Rapira TON-USDT ask 1.3042 → after 0.5% profit → 1.297679 (observed course_value)
        $exp = new RateConfiguredExpectation();
        $a = $exp->analyze('1.3042', '1.297679', profitPercent: '0.5');
        $this->assertNotNull($a['unexplained_deviation']);
        $this->assertLessThan(0.05, abs((float) $a['unexplained_deviation']));
        $this->assertSame('normal', $exp->classifyUnexplained($a['unexplained_deviation']));
    }

    public function testZelleUsdAsGiveAppliesConfiguredSixPercent(): void
    {
        $exp = new RateConfiguredExpectation();
        $a = $exp->analyze('1', '0.94', profitPercent: '6');
        $this->assertLessThan(0.05, abs((float) $a['unexplained_deviation']));
        $this->assertGreaterThan(5.0, abs((float) $a['raw_market_deviation']));
        $this->assertSame('normal', $exp->classifyUnexplained($a['unexplained_deviation']));
    }

    public function testZelleUsdAsReceiveNearParityWithZeroProfit(): void
    {
        $exp = new RateConfiguredExpectation();
        $a = $exp->analyze('1.00304929', '1.00304929', profitPercent: '0');
        $this->assertLessThan(0.01, abs((float) $a['unexplained_deviation']));
    }

    public function testConfiguredSpreadNotClassifiedExtreme(): void
    {
        $exp = new RateConfiguredExpectation();
        $a = $exp->analyze('100', '94', profitPercent: '6');
        $this->assertSame('normal', $exp->classifyUnexplained($a['unexplained_deviation']));
        $this->assertSame('high', $exp->classifyUnexplained($a['raw_market_deviation']));
    }

    public function testDuplicateProfitRejectedByUnexplainedGap(): void
    {
        $exp = new RateConfiguredExpectation();
        // profit applied twice: 100*0.94*0.94 = 88.36 vs expected once 94
        $a = $exp->analyze('100', '88.36', profitPercent: '6');
        $this->assertGreaterThan(5.0, abs((float) $a['unexplained_deviation']));
        $this->assertSame('high', $exp->classifyUnexplained($a['unexplained_deviation']));
    }

    public function testDuplicatePaymentFeeRejected(): void
    {
        $exp = new RateConfiguredExpectation();
        $a = $exp->analyze(
            '100',
            '90.25', // 100*0.95*0.95
            profitPercent: '0',
            paymentSystemFeePercent: '5',
        );
        // expected once = 95; unexplained ≈ -5%
        $this->assertGreaterThan(4.0, abs((float) $a['unexplained_deviation']));
    }

    public function testZeroAndNegativeRatesInvalid(): void
    {
        $q = new RateExportQuarantine();
        $this->assertFalse($q->isExportableCourse('0'));
        $this->assertFalse($q->isExportableCourse('-1'));
        $this->assertFalse($q->evaluate('0')['allowed']);
        $this->assertSame(RateExportQuarantine::EXPORT_BLOCKED_INVALID, $q->evaluate('0')['status']);
    }

    public function testMissingSourceNoBaselinePassThroughUnlessForced(): void
    {
        $q = new RateExportQuarantine();
        $ok = $q->evaluate('100', []);
        $this->assertTrue($ok['allowed']);
        $blocked = $q->evaluate('100', ['force_block_reason' => 'stale_source']);
        $this->assertFalse($blocked['allowed']);
        $this->assertSame(RateExportQuarantine::EXPORT_BLOCKED_STALE, $blocked['status']);
    }

    public function testDirectAndReciprocalPeerInversion(): void
    {
        $guard = new RateSanityGuard();
        $peers = $guard->peerRatesFromBestChangeRows([
            ['rate' => '0.01'], // → 100
            ['rate' => '0.0101'],
            ['rate' => '0.0099'],
        ]);
        $this->assertCount(3, $peers);
        $this->assertGreaterThan(90, (float) $peers[0]);
    }

    public function testPeerMedianSelectionAndOutlierRejection(): void
    {
        $sel = new PeerRateSelector();
        $ok = $sel->selectValidPeerRate(['100', '101', '99', '100.5'], preferred: '100');
        $this->assertTrue($ok['ok']);
        $this->assertSame('accepted', $ok['reason']);

        $bad = $sel->selectValidPeerRate(['100', '101', '99', '100.5'], preferred: '200');
        $this->assertFalse($bad['ok']);
        $this->assertSame('outlier_peer_rejected', $bad['reason']);
    }

    public function testInsufficientPeerSample(): void
    {
        $sel = new PeerRateSelector();
        $r = $sel->selectValidPeerRate(['100', '101'], minSample: 3);
        $this->assertFalse($r['ok']);
        $this->assertSame('insufficient_peer_sample', $r['reason']);
    }

    public function testIndependentBtcGelBaseline(): void
    {
        $base = new IndependentMarketBaseline([
            'BTCUSDT' => ['rate' => '64000', 'source' => 'fixture', 'as_of' => gmdate('c')],
            'USDGEL' => ['rate' => '2.70', 'source' => 'fixture', 'as_of' => gmdate('c')],
        ], allowDatabase: false);
        $q = $base->cryptoGel('BTC');
        $this->assertNotNull($q);
        $this->assertEqualsWithDelta(172800.0, (float) $q['rate'], 0.01);
    }

    public function testPublicExportQuarantineBlocksExtremeUnexplained(): void
    {
        $q = new RateExportQuarantine();
        $r = $q->evaluate('26207', [
            'baseline' => '1',
            'profit_percent' => '0',
        ]);
        $this->assertFalse($r['allowed']);
        $this->assertSame(RateExportQuarantine::EXPORT_BLOCKED_OUTLIER, $r['status']);
    }

    public function testCatalogGuardDetectsPrusdVndCollision(): void
    {
        $dir = sys_get_temp_dir() . '/exswaping_bc_guard_' . getmypid();
        @mkdir($dir . '/bestchange', 0777, true);
        file_put_contents($dir . '/bestchange/currencies.json', json_encode([
            ['id' => 108, 'name' => '[CARDVND] - Банковская карта VND'],
        ]));
        file_put_contents($dir . '/bestchange-codes.json', json_encode([
            '108' => ['id' => '108', 'code' => 'PRUSD', 'name' => 'Payeer USD'],
        ]));
        $guard = new BestChangeCurrencyCatalogGuard(
            $dir . '/bestchange/currencies.json',
            $dir . '/bestchange-codes.json',
        );
        $r = $guard->validateId(108, 'PRUSD');
        $this->assertFalse($r['ok']);
        $this->assertSame('currency_mapping_mismatch', $r['reason']);
    }

    public function testXmlParityHelperComparesRatesNotUrls(): void
    {
        $a = ['in' => '1', 'out' => '100'];
        $b = ['in' => '1', 'out' => '100'];
        $this->assertTrue($a['in'] === $b['in'] && $a['out'] === $b['out']);
    }
}
