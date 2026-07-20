<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\IndependentMarketBaseline;
use App\Services\Rates\RateConfiguredExpectation;
use App\Services\Rates\RateExportQuarantine;
use PHPUnit\Framework\TestCase;

final class ZecSbpRateIncidentTest extends TestCase
{
    public function testZecRubIndependentBaselineOrientation(): void
    {
        $base = new IndependentMarketBaseline([
            'ZECUSDT' => ['rate' => '536.5', 'source' => 'fixture_binance', 'as_of' => gmdate('c')],
            'USDRUB' => ['rate' => '78.2645', 'source' => 'fixture_cbr', 'as_of' => gmdate('c')],
        ], allowDatabase: false);

        $q = $base->cryptoRub('ZEC');
        $this->assertNotNull($q);
        // 536.5 * 78.2645 ≈ 41988.9 RUB per 1 ZEC (receive per source unit)
        $this->assertEqualsWithDelta(41988.9, (float) $q['rate'], 1.0);
    }

    public function testReportedZecSbpOutlierBlockedAgainstIndependentBaseline(): void
    {
        $baseline = '41988.9';
        $incidentRate = '45722.79'; // BestChange-reported Exswaping export
        $q = new RateExportQuarantine();
        $decision = $q->evaluate($incidentRate, [
            'baseline' => $baseline,
            'profit_percent' => '0',
        ]);
        $this->assertFalse($decision['allowed']);
        $this->assertSame(RateExportQuarantine::EXPORT_BLOCKED_OUTLIER, $decision['status']);

        $analysis = (new RateConfiguredExpectation())->analyze(
            baseline: $baseline,
            actual: $incidentRate,
            profitPercent: '0',
        );
        $this->assertNotNull($analysis['unexplained_deviation']);
        $this->assertGreaterThan(7.0, abs((float) $analysis['unexplained_deviation']));
    }

    public function testConfiguredSpreadRemainsAllowed(): void
    {
        $q = new RateExportQuarantine();
        $r = $q->evaluate('103', [
            'baseline' => '100',
            'profit_percent' => '3',
        ]);
        $this->assertTrue($r['allowed']);
    }

    public function testGramIsNeverUsedAsTonBaselineAsset(): void
    {
        $base = new IndependentMarketBaseline([
            'TONUSDT' => ['rate' => '5', 'source' => 'fixture', 'as_of' => gmdate('c')],
            'USDRUB' => ['rate' => '80', 'source' => 'fixture', 'as_of' => gmdate('c')],
        ], allowDatabase: false);
        $ton = $base->cryptoRub('TON');
        $this->assertNotNull($ton);
        $this->assertEqualsWithDelta(400.0, (float) $ton['rate'], 0.01);
    }

    public function testCircularBestChangeSourceIsNotAnIndependentBaseline(): void
    {
        // IndependentMarketBaseline only reads parser_exchange / injected fixtures —
        // never BestChange competitor XML. A missing ZEC feed must fail closed.
        $base = new IndependentMarketBaseline([], allowDatabase: false);
        $this->assertNull($base->cryptoRub('ZEC'));
        $q = new RateExportQuarantine();
        $decision = $q->evaluate('42000', []);
        $this->assertFalse($decision['allowed']);
        $this->assertSame(RateExportQuarantine::EXPORT_BLOCKED_NO_BASELINE, $decision['status']);
    }
}
