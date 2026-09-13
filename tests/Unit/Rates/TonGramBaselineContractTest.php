<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\IndependentMarketBaseline;
use PHPUnit\Framework\TestCase;

/**
 * TONUSDT is the canonical GRAM/TON market peg. Never invent a price;
 * fail closed on missing, stale, or non-positive baselines.
 */
final class TonGramBaselineContractTest extends TestCase
{
    public function testGramPegsThroughTonAssetNotASeparateMarket(): void
    {
        $this->assertSame('TON', IndependentMarketBaseline::assetFromCode('TON'));
        $this->assertSame('TON', IndependentMarketBaseline::assetFromCode('GRAM'));
        $this->assertSame('TON', IndependentMarketBaseline::assetFromCode('GRAMTON'));
    }

    public function testPrimaryHealthyTonUsdtYieldsFreshCryptoRub(): void
    {
        $imb = new IndependentMarketBaseline([
            'TONUSDT' => [
                'rate' => '1.600000',
                'source' => 'parser_exchange:[binance_ton-usdt]',
                'as_of' => gmdate('c'),
            ],
            'USDRUB' => [
                'rate' => '84.24456',
                'source' => 'russiancentralbank',
                'as_of' => gmdate('c'),
            ],
        ], false);

        $ton = $imb->quote('TONUSDT');
        $this->assertNotNull($ton);
        $this->assertSame('1.600000000000000000', $ton['rate']);
        $this->assertLessThan(IndependentMarketBaseline::CRYPTO_MAX_AGE_SECONDS, $ton['age_seconds']);

        $rub = $imb->cryptoRub('TON');
        $this->assertNotNull($rub);
        $this->assertGreaterThan(0, (float) $rub['rate']);
        $this->assertStringContainsString('binance_ton-usdt', $rub['source']);
    }

    public function testApprovedFallbackIsUsedWhenPrimarySymbolMissing(): void
    {
        $imb = new IndependentMarketBaseline([
            'TONUSD' => [
                'rate' => '1.599000',
                'source' => 'parser_exchange:[approved_fallback_ton-usd]',
                'as_of' => gmdate('c'),
            ],
            'USDRUB' => [
                'rate' => '84.24456',
                'source' => 'russiancentralbank',
                'as_of' => gmdate('c'),
            ],
        ], false);

        $this->assertNull($imb->quote('TONUSDT'));
        $rub = $imb->cryptoRub('TON');
        $this->assertNotNull($rub);
        $this->assertStringContainsString('approved_fallback_ton-usd', $rub['source']);
    }

    public function testStaleTonUsdtFailsClosedForDerivedQuotes(): void
    {
        $stale = gmdate('c', time() - IndependentMarketBaseline::CRYPTO_MAX_AGE_SECONDS - 30);
        $imb = new IndependentMarketBaseline([
            'TONUSDT' => [
                'rate' => '1.600000',
                'source' => 'stale-binance',
                'as_of' => $stale,
            ],
            'USDRUB' => [
                'rate' => '84.24456',
                'source' => 'russiancentralbank',
                'as_of' => gmdate('c'),
            ],
        ], false);

        $this->assertGreaterThan(
            IndependentMarketBaseline::CRYPTO_MAX_AGE_SECONDS,
            (int) ($imb->quote('TONUSDT')['age_seconds'] ?? 0)
        );
        $this->assertNull($imb->cryptoRub('TON'));
        $this->assertNull($imb->cryptoViaUsdt('GRAM', 'USDTTRC20'));
    }

    public function testMissingTonBaselineFailsClosed(): void
    {
        $imb = new IndependentMarketBaseline([
            'USDRUB' => [
                'rate' => '84.24456',
                'source' => 'russiancentralbank',
                'as_of' => gmdate('c'),
            ],
        ], false);

        $this->assertNull($imb->quote('TONUSDT'));
        $this->assertNull($imb->cryptoRub('TON'));
        $this->assertNull($imb->cryptoViaUsdt('USDTTRC20', 'GRAM'));
    }

    public function testZeroOrInvalidTonPegIsRejected(): void
    {
        foreach (['0', '0.0', '-1', ''] as $bad) {
            $imb = new IndependentMarketBaseline([
                'TONUSDT' => [
                    'rate' => $bad,
                    'source' => 'invalid-peg',
                    'as_of' => gmdate('c'),
                ],
            ], false);
            $this->assertNull($imb->quote('TONUSDT'), "rate={$bad} must not be usable");
        }
    }

    public function testGramDerivedQuoteUsesTonUsdtPrecision(): void
    {
        $imb = new IndependentMarketBaseline([
            'TONUSDT' => [
                'rate' => '1.600000',
                'source' => 'parser_exchange:[binance_ton-usdt]',
                'as_of' => gmdate('c'),
            ],
        ], false);

        $this->assertSame('TON', IndependentMarketBaseline::assetFromCode('GRAM'));
        $this->assertSame('USDT', IndependentMarketBaseline::assetFromCode('USDTTRC20'));
        $cross = $imb->cryptoViaUsdt('GRAM', 'USDTTRC20');
        $this->assertNotNull($cross);
        $this->assertSame('1.600000000000000000', $cross['rate']);
        $this->assertSame('crypto_to_stable_via_usdt', $cross['path']);

        $toGram = $imb->cryptoViaUsdt('USDTTRC20', 'GRAM');
        $this->assertNotNull($toGram);
        $this->assertSame('stable_to_crypto_via_usdt', $toGram['path']);
        $this->assertSame('0.625000000000000000', $toGram['rate']);

        $gramRub = $imb->cryptoRub('GRAM');
        $this->assertNull($gramRub, 'GRAM→RUB still requires a USDRUB baseline');
    }

    public function testOutlierAnnotationDoesNotInventAMedianWhenOnlyInjectedPrimaryExists(): void
    {
        $imb = new IndependentMarketBaseline([
            'TONUSDT' => [
                'rate' => '1.600000',
                'source' => 'parser_exchange:[binance_ton-usdt]',
                'as_of' => gmdate('c'),
            ],
        ], false);
        $q = $imb->quote('TONUSDT');
        $this->assertNotNull($q);
        $this->assertNull($q['divergence']);
        $this->assertSame(1, $q['sample_size']);
    }
}
