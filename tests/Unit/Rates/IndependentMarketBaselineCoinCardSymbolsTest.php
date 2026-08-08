<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\IndependentMarketBaseline;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class IndependentMarketBaselineCoinCardSymbolsTest extends TestCase
{
    public function test_asset_from_code_covers_coin_card_matrix_assets(): void
    {
        $this->assertSame('BCH', IndependentMarketBaseline::assetFromCode('BCH'));
        $this->assertSame('DOT', IndependentMarketBaseline::assetFromCode('DOT'));
        $this->assertSame('SHIB', IndependentMarketBaseline::assetFromCode('SHIBERC20'));
        $this->assertSame('SHIB', IndependentMarketBaseline::assetFromCode('SHIBBEP20'));
        $this->assertSame('TUSD', IndependentMarketBaseline::assetFromCode('TUSDERC20'));
        $this->assertSame('PEPE', IndependentMarketBaseline::assetFromCode('Pepe'));
        $this->assertSame('TON', IndependentMarketBaseline::assetFromCode('GRAM'));
    }

    public function test_symbol_candidates_include_new_fiat_and_asset_legs(): void
    {
        $imb = new IndependentMarketBaseline(allowDatabase: false);
        $m = new ReflectionMethod(IndependentMarketBaseline::class, 'symbolCandidates');
        $m->setAccessible(true);
        $this->assertNotSame([], $m->invoke($imb, 'BCHUSDT'));
        $this->assertNotSame([], $m->invoke($imb, 'DOTUSDT'));
        $this->assertNotSame([], $m->invoke($imb, 'SHIBUSDT'));
        $this->assertNotSame([], $m->invoke($imb, 'USDINR'));
        $this->assertNotSame([], $m->invoke($imb, 'USDKGS'));
        $this->assertNotSame([], $m->invoke($imb, 'USDTJS'));
        $this->assertNotSame([], $m->invoke($imb, 'USDUZS'));
    }
}
