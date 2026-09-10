<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\Currency;
use App\Models\DirectionExchange;
use App\Services\Rates\CoinZelleExternalPositionController;
use App\Settings\BestChangeConfig;
use PHPUnit\Framework\TestCase;

final class CoinZelleExternalPositionControllerTest extends TestCase
{
    public function test_round_bcmath_matches_export_half_up(): void
    {
        $c = $this->controller(enabled: true);
        $this->assertSame('1.0010', $c->roundBcmath('1.001000079960687187', 4));
        $this->assertSame('1.0011', $c->roundBcmath('1.00105', 4));
        $this->assertSame('1.0011', $c->roundBcmath('1.0010500001', 4));
    }

    public function test_minimum_winning_tick_survives_xml_rounding(): void
    {
        $c = $this->controller(enabled: true);
        $top1 = '1.001000079960687187';
        $out = $c->minimumWinningExportOut($top1, 4);
        $this->assertSame('1.0011', $out);
        $course = $c->minimumCourseForExportOut($out, 4);
        $this->assertSame('1.0011', $c->exportOut($course, 4));
        $this->assertTrue(bccomp($c->exportOut($course, 4), $top1, 18) === 1);
    }

    public function test_beats_live_top1_at_0011_with_next_tick(): void
    {
        $c = $this->controller(enabled: true, floorBps: -1);
        $top1 = '1.001100099990088218';
        $out = $c->minimumWinningExportOut($top1, 4);
        $this->assertSame('1.0012', $out);
        $course = $c->minimumCourseForExportOut($out, 4);
        $margin = $c->marginBps($top1, $course);
        $this->assertLessThan(0, $margin);
        $this->assertGreaterThanOrEqual(-1.0, $margin);
    }

    public function test_margin_guard_blocks_when_floor_zero(): void
    {
        $c = $this->controller(enabled: true, floorBps: 0, allow: [1526]);
        $dir = $this->fakeDirection(1526);
        $book = [
            ['changer' => 1160, 'rate' => '0.998901'], // inv ≈ 1.0011
            ['changer' => 1418, 'rate' => '0.9990009192'],
            ['changer' => 1328, 'rate' => '0.9991'],
        ];
        $adj = $c->adjust($dir, '1.001000079960687187', $book, 4);
        $this->assertFalse($adj['applied']);
        $this->assertTrue($adj['margin_guard_blocked_top1']);
        $this->assertSame('margin_guard_blocked_top1', $adj['reason']);
    }

    public function test_allowlist_skips_other_directions(): void
    {
        $c = $this->controller(enabled: true, allow: [1526]);
        $dir = $this->fakeDirection(9999);
        $book = [
            ['changer' => 1, 'rate' => '0.9990009192'],
            ['changer' => 2, 'rate' => '0.9991'],
            ['changer' => 3, 'rate' => '0.9992'],
        ];
        $adj = $c->adjust($dir, '1.001000079960687187', $book, 4);
        $this->assertSame('direction_not_in_allowlist', $adj['reason']);
    }

    public function test_outlier_guard(): void
    {
        $c = $this->controller(enabled: true, allow: [3138], outlierSep: 200);
        $dir = $this->fakeDirection(3138);
        $book = [
            ['changer' => 1, 'rate' => '0.5'], // inv 2.0
            ['changer' => 2, 'rate' => '0.74'], // inv ~1.35
            ['changer' => 3, 'rate' => '0.741'],
        ];
        $adj = $c->adjust($dir, '1.35', $book, 4);
        $this->assertTrue($adj['outlier_top1']);
        $this->assertSame('outlier_top1_guard', $adj['reason']);
        $this->assertFalse($adj['applied']);
    }

    /**
     * @param  int[]  $allow
     */
    private function controller(
        bool $enabled,
        float $floorBps = -1,
        array $allow = [],
        float $outlierSep = 200,
    ): CoinZelleExternalPositionController {
        $cfg = $this->createMock(BestChangeConfig::class);
        $cfg->method('coinZelleExternalPositionEnabled')->willReturn($enabled);
        $cfg->method('coinZelleExternalPositionTarget')->willReturn(1);
        $cfg->method('coinZelleCompetitiveEdgeMode')->willReturn('minimum_winning_increment');
        $cfg->method('coinZelleMinSafeMarginVsTop1Bps')->willReturn($floorBps);
        $cfg->method('coinZelleControllerDirectionIds')->willReturn($allow);
        $cfg->method('coinZelleMinBookDepth')->willReturn(3);
        $cfg->method('coinZelleOutlierGuardEnabled')->willReturn(true);
        $cfg->method('coinZelleOutlierSepBps')->willReturn($outlierSep);
        $cfg->method('ownExchangerId')->willReturn(1522);

        return new CoinZelleExternalPositionController($cfg);
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
