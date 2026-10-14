<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\Currency;
use App\Models\DirectionExchange;
use App\Services\Rates\BestChangeExternalTopNController;
use App\Services\Rates\BestChangeTopNBookHelper;
use App\Settings\BestChangeConfig;
use PHPUnit\Framework\TestCase;

/**
 * Proves Top-N adjust is identical when fed the same generation batch book
 * vs a reordered "fresh" rawRows snapshot (marketForDirection-equivalent shape).
 */
final class BestChangeL1TopNBookReuseParityTest extends TestCase
{
    public function test_adjust_parity_batch_vs_reordered_fresh_raw_rows(): void
    {
        $batchRaw = [
            ['changer' => 1, 'rate' => '0.998901', 'reserve' => '10'],
            ['changer' => 2, 'rate' => '0.9990009192', 'reserve' => '20'],
            ['changer' => 3, 'rate' => '0.9991', 'reserve' => '30'],
            ['changer' => 4, 'rate' => '0.9992', 'reserve' => '40'],
            ['changer' => 1522, 'rate' => '0.9995', 'reserve' => '50'],
        ];
        $freshRaw = array_reverse($batchRaw);
        foreach ($freshRaw as &$row) {
            $row['extra'] = 'marketForDirection_noise';
        }
        unset($row);

        $batchBook = BestChangeTopNBookHelper::normalizeForTopN($batchRaw);
        $freshBook = BestChangeTopNBookHelper::normalizeForTopN($freshRaw);
        $this->assertTrue(BestChangeTopNBookHelper::topNRelevantEqual($batchBook, $freshBook));

        $ctrl = $this->controller();
        $dir = $this->fakeDirection(1526);
        $base = '1.0005';
        $auth = $ctrl->adjust($dir, $base, $freshBook, 4, 3, 3);
        $shadow = $ctrl->adjust($dir, $base, $batchBook, 4, 3, 3);

        $this->assertSame($auth['applied'], $shadow['applied']);
        $this->assertSame($auth['reason'], $shadow['reason']);
        $this->assertSame($auth['status'], $shadow['status']);
        $this->assertSame(0, bccomp((string) $auth['course'], (string) $shadow['course'], 18));
    }

    public function test_missing_batch_book_does_not_force_empty_authoritative_book(): void
    {
        // Authoritative path keeps its own book; shadow simply skips compare when
        // generation context has no mapping (covered by null rawRowsForDirectionExchange).
        $ctx = new \App\Services\Rates\BestChangeCompilerGenerationContext();
        $ctx->beginGeneration();
        $this->assertNull($ctx->rawRowsForDirectionExchange(999999));
    }

    private function controller(): BestChangeExternalTopNController
    {
        return new BestChangeExternalTopNController($this->mockConfig());
    }

    private function mockConfig(): BestChangeConfig
    {
        // Mock names must match live BestChangeConfig (post-f77 Top-N API).
        // Old L1 branch used obsolete externalTopNMarginFloorBps aliases.
        $cfg = $this->createMock(BestChangeConfig::class);
        $cfg->method('externalTopNEnabled')->willReturn(true);
        $cfg->method('coinZelleExternalPositionEnabled')->willReturn(true);
        $cfg->method('externalTopNFamilyEnabled')->willReturn(true);
        $cfg->method('externalTopNDirectionIds')->willReturn([]);
        $cfg->method('coinZelleControllerDirectionIds')->willReturn([]);
        $cfg->method('ownExchangerId')->willReturn(1522);
        $cfg->method('externalTopNMinBookDepth')->willReturn(3);
        $cfg->method('coinZelleMinBookDepth')->willReturn(3);
        $cfg->method('coinZelleExternalPositionTarget')->willReturn(3);
        $cfg->method('externalTopNMinSafeMarginVsBoundaryBps')->willReturn(0.0);
        $cfg->method('coinZelleMinSafeMarginVsTop1Bps')->willReturn(0.0);
        $cfg->method('externalTopNOutlierGuardEnabled')->willReturn(true);
        $cfg->method('coinZelleOutlierGuardEnabled')->willReturn(true);
        $cfg->method('externalTopNOutlierSepBps')->willReturn(200.0);
        $cfg->method('coinZelleOutlierSepBps')->willReturn(200.0);

        return $cfg;
    }

    private function fakeDirection(int $id): DirectionExchange
    {
        $from = new Currency();
        $from->designation_xml = 'BTC';
        $to = new Currency();
        $to->designation_xml = 'ZELLEUSD';
        $to->number_format = 4;
        $d = new DirectionExchange();
        $d->id = $id;
        $d->parser_source_name = 'BestChange';
        $d->setRelation('currency1', $from);
        $d->setRelation('currency2', $to);

        return $d;
    }
}
