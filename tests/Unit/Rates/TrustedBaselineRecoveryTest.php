<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\IndependentMarketBaseline;
use App\Services\Rates\RateExportQuarantine;
use App\Services\Rates\RubFamilyPremiumPolicy;
use PHPUnit\Framework\TestCase;

final class TrustedBaselineRecoveryTest extends TestCase
{
    public function testNoBaselineNeverExportedPublicly(): void
    {
        $q = new RateExportQuarantine();
        $blocked = $q->evaluate('100', []);
        $this->assertFalse($blocked['allowed']);
        $this->assertSame(RateExportQuarantine::EXPORT_BLOCKED_NO_BASELINE, $blocked['status']);
    }

    public function testCryptoViaUsdtBridgeOrientation(): void
    {
        $base = new IndependentMarketBaseline([
            'BTCUSDT' => ['rate' => '64000', 'source' => 'fx_btc', 'as_of' => gmdate('c')],
            'ETHUSDT' => ['rate' => '3200', 'source' => 'fx_eth', 'as_of' => gmdate('c')],
        ], allowDatabase: false);

        $q = $base->cryptoViaUsdt('BTC', 'ETH');
        $this->assertNotNull($q);
        $this->assertEqualsWithDelta(20.0, (float) $q['rate'], 0.0001);
        $this->assertSame('crypto_to_crypto_via_usdt', $q['path']);
    }

    public function testZecRubBridgeUsesFreshInjectedFeed(): void
    {
        $base = new IndependentMarketBaseline([
            'ZECUSDT' => ['rate' => '535.53', 'source' => 'binance', 'as_of' => gmdate('c')],
            'USDRUB' => ['rate' => '78.3987', 'source' => 'cbr', 'as_of' => gmdate('c')],
        ], allowDatabase: false);
        $q = $base->cryptoRub('ZEC');
        $this->assertNotNull($q);
        $this->assertEqualsWithDelta(535.53 * 78.3987, (float) $q['rate'], 1.0);
    }

    public function testRubFamilyPremiumNotAppliedUntilApproved(): void
    {
        $draft = new RubFamilyPremiumPolicy([
            'approved' => false,
            'families' => [
                'SBPRUB' => [
                    'destination_codes' => ['SBPRUB'],
                    'proposed_configured_premium_max_pct' => 8.0,
                ],
            ],
        ]);
        $this->assertFalse($draft->isApproved());
        $this->assertNull($draft->explainedPremiumPercent('SBPRUB'));

        $approved = new RubFamilyPremiumPolicy([
            'approved' => true,
            'families' => [
                'SBPRUB' => [
                    'destination_codes' => ['SBPRUB'],
                    'proposed_configured_premium_max_pct' => 8.0,
                ],
            ],
        ]);
        $this->assertTrue($approved->isApproved());
        $this->assertSame(8.0, $approved->explainedPremiumPercent('SBPRUB'));
    }

    public function testAssetFromCodeMapsNetworks(): void
    {
        $this->assertSame('USDT', IndependentMarketBaseline::assetFromCode('USDTTRC20'));
        $this->assertSame('BNB', IndependentMarketBaseline::assetFromCode('BNBBEP20'));
        $this->assertSame('ZEC', IndependentMarketBaseline::assetFromCode('ZEC'));
        $this->assertNull(IndependentMarketBaseline::assetFromCode('DASH'));
    }

    public function testStaleZecQuoteRejected(): void
    {
        $stale = gmdate('c', time() - 3600);
        $base = new IndependentMarketBaseline([
            'ZECUSDT' => ['rate' => '535', 'source' => 'stale', 'as_of' => $stale],
            'USDRUB' => ['rate' => '78', 'source' => 'cbr', 'as_of' => gmdate('c')],
        ], allowDatabase: false);
        $this->assertNull($base->cryptoRub('ZEC'));
    }
}
