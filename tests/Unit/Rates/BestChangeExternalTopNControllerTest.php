<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\Currency;
use App\Models\DirectionExchange;
use App\Services\Rates\BestChangeExternalTopNController;
use App\Services\Rates\CoinZelleExternalPositionController;
use App\Settings\BestChangeConfig;
use PHPUnit\Framework\TestCase;

final class BestChangeExternalTopNControllerTest extends TestCase
{
    public function test_at_or_better_keeps_rank2_when_target_is_5(): void
    {
        $c = $this->controller();
        $dir = $this->fakeDirection(1526);
        // Own exchanger already #2 — must not worsen toward exact #5.
        $book = [
            ['changer' => 1, 'rate' => '0.998'],      // inv ~1.002
            ['changer' => 1522, 'rate' => '0.9985'],  // us #2
            ['changer' => 2, 'rate' => '0.999'],
            ['changer' => 3, 'rate' => '0.9992'],
            ['changer' => 4, 'rate' => '0.9994'],
            ['changer' => 5, 'rate' => '0.9996'],
        ];
        $adj = $c->adjust($dir, '1.0015', $book, 4, 5, 5);
        $this->assertFalse($adj['applied']);
        $this->assertSame(BestChangeExternalTopNController::STATUS_BETTER_THAN_TARGET, $adj['status']);
        $this->assertSame('1.0015', $adj['course']);
        $this->assertSame(2, $adj['actual_rank']);
    }

    public function test_target_3_applies_when_rank_worse_than_3(): void
    {
        $c = $this->controller();
        $dir = $this->fakeDirection(1526);
        $book = [
            ['changer' => 1, 'rate' => '0.998901'], // #1 inv≈1.0011
            ['changer' => 2, 'rate' => '0.9990009192'], // #2
            ['changer' => 3, 'rate' => '0.9991'], // #3 boundary
            ['changer' => 4, 'rate' => '0.9992'],
            ['changer' => 1522, 'rate' => '0.9995'], // us worse
        ];
        $adj = $c->adjust($dir, '1.0005', $book, 4, 3, 3);
        $this->assertTrue($adj['applied']);
        $this->assertSame(3, $adj['effective_target']);
        $this->assertSame('applied_minimum_winning_tick', $adj['reason']);
    }

    public function test_thin_book_clamps_target(): void
    {
        $c = $this->controller(minDepth: 3);
        $dir = $this->fakeDirection(1526);
        $book = [
            ['changer' => 1, 'rate' => '0.998'],
            ['changer' => 2, 'rate' => '0.999'],
            ['changer' => 3, 'rate' => '0.9995'],
            ['changer' => 1522, 'rate' => '1.01'], // us last
        ];
        $adj = $c->adjust($dir, '0.99', $book, 4, 5, 5);
        $this->assertSame(3, $adj['effective_target']);
    }

    public function test_non_coin_zelle_not_eligible(): void
    {
        $c = $this->controller();
        $from = new Currency();
        $from->designation_xml = 'BTC';
        $to = new Currency();
        $to->designation_xml = 'ETH';
        $to->number_format = 8;
        $d = new DirectionExchange();
        $d->id = 1;
        $d->parser_source_name = 'BestChange';
        $d->setRelation('currency1', $from);
        $d->setRelation('currency2', $to);
        $this->assertFalse($c->isEligible($d));
    }

    public function test_coin_zelle_facade_parity_margin_guard(): void
    {
        $cfg = $this->mockConfig(floorBps: 0, allow: [1526]);
        $topN = new BestChangeExternalTopNController($cfg);
        $facade = new CoinZelleExternalPositionController($cfg, $topN);
        $dir = $this->fakeDirection(1526);
        $book = [
            ['changer' => 1160, 'rate' => '0.998901'],
            ['changer' => 1418, 'rate' => '0.9990009192'],
            ['changer' => 1328, 'rate' => '0.9991'],
        ];
        $adj = $facade->adjust($dir, '1.001000079960687187', $book, 4);
        $this->assertFalse($adj['applied']);
        $this->assertTrue($adj['margin_guard_blocked_top1']);
        $this->assertSame('margin_guard_blocked_top1', $adj['reason']);
    }

    private function controller(int $minDepth = 3): BestChangeExternalTopNController
    {
        return new BestChangeExternalTopNController($this->mockConfig(floorBps: -1, allow: [1526], minDepth: $minDepth));
    }

    /**
     * @param  int[]  $allow
     */
    private function mockConfig(float $floorBps = -1, array $allow = [1526], int $minDepth = 3): BestChangeConfig
    {
        $cfg = $this->createMock(BestChangeConfig::class);
        $cfg->method('externalTopNEnabled')->willReturn(false);
        $cfg->method('coinZelleExternalPositionEnabled')->willReturn(true);
        $cfg->method('coinZelleExternalPositionTarget')->willReturn(1);
        $cfg->method('externalTopNFamilies')->willReturn(['COIN_TO_ZELLE']);
        $cfg->method('externalTopNFamilyEnabled')->willReturnCallback(
            static fn (string $f): bool => $f === 'COIN_TO_ZELLE'
        );
        $cfg->method('externalTopNDirectionIds')->willReturn([]);
        $cfg->method('coinZelleControllerDirectionIds')->willReturn($allow);
        $cfg->method('externalTopNMinBookDepth')->willReturn($minDepth);
        $cfg->method('coinZelleMinBookDepth')->willReturn($minDepth);
        $cfg->method('externalTopNMinSafeMarginVsBoundaryBps')->willReturn($floorBps);
        $cfg->method('coinZelleMinSafeMarginVsTop1Bps')->willReturn($floorBps);
        $cfg->method('externalTopNOutlierGuardEnabled')->willReturn(true);
        $cfg->method('coinZelleOutlierGuardEnabled')->willReturn(true);
        $cfg->method('externalTopNOutlierSepBps')->willReturn(200.0);
        $cfg->method('coinZelleOutlierSepBps')->willReturn(200.0);
        $cfg->method('ownExchangerId')->willReturn(1522);

        return $cfg;
    }

    private function fakeDirection(int $id): DirectionExchange
    {
        $from = new Currency();
        $from->designation_xml = 'USDTTRC20';
        $to = new Currency();
        $to->designation_xml = 'ZELLEUSD';
        $to->number_format = 4;

        $d = new DirectionExchange();
        $d->id = $id;
        $d->parser_source_name = 'BestChange';
        $d->setRelation('currency1', $from);
        $d->setRelation('currency2', $to);
        $d->setRelation('bestchange_directions', collect());

        return $d;
    }
}
