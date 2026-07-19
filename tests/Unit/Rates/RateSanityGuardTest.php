<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\RateSanityGuard;
use PHPUnit\Framework\TestCase;

final class RateSanityGuardTest extends TestCase
{
    private RateSanityGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new RateSanityGuard();
    }

    public function testRejectsNonPositiveCandidate(): void
    {
        $r = $this->guard->evaluate('0', ['100']);
        $this->assertFalse($r['ok']);
        $this->assertSame('reject', $r['action']);
    }

    public function testPassesNearBaseline(): void
    {
        $r = $this->guard->evaluate('168000', ['167000', '168000', '169000'], '0.12');
        $this->assertTrue($r['ok']);
        $this->assertSame('pass', $r['action']);
        $this->assertSame($r['candidate'], $r['effective']);
    }

    public function testClampsExtremeAboveBaselineMatchingHistoricalBtcGelPattern(): void
    {
        // Historical defect shape: published ≈ spot × 1.20 while peers ≈ spot
        $baselinePeers = ['168783.97', '168500', '169000', '168900'];
        $inflated = '204005.66';
        $r = $this->guard->evaluate($inflated, $baselinePeers, '0.12', clamp: true);
        $this->assertTrue($r['ok']);
        $this->assertSame('clamp', $r['action']);
        $this->assertSame('above_max_deviation', $r['reason']);
        $this->assertLessThan(1.13, (float) $r['effective'] / (float) $r['baseline']);
        $this->assertGreaterThan(1.0, (float) $r['effective'] / (float) $r['baseline']);
    }

    public function testRejectModeDoesNotPublishExtreme(): void
    {
        $r = $this->guard->evaluate('204005.66', ['168783.97'], '0.12', clamp: false);
        $this->assertFalse($r['ok']);
        $this->assertSame('reject', $r['action']);
        $this->assertSame('0', $r['effective']);
    }

    public function testPeerRatesFromBestChangeRowsInvertsLikeRateCalculator(): void
    {
        // field rate = BTC per GEL ≈ 1/168000
        $rows = [
            ['rate' => '0.000005952'],
            ['rate' => '0.000005950'],
            ['rate' => '0.000005960'],
        ];
        $peers = $this->guard->peerRatesFromBestChangeRows($rows, 'rate');
        $this->assertCount(3, $peers);
        foreach ($peers as $p) {
            $this->assertGreaterThan(160000, (float) $p);
            $this->assertLessThan(180000, (float) $p);
        }
    }

    public function testPercentageUnitIsFractionNotPercentPoints(): void
    {
        // 0.12 means 12%, not 0.12%
        $r = $this->guard->evaluate('112', ['100'], '0.12', clamp: true);
        $this->assertSame('pass', $r['action']);
        $r2 = $this->guard->evaluate('120', ['100'], '0.12', clamp: true);
        $this->assertSame('clamp', $r2['action']);
    }

    public function testDuplicateMarginRegressionShape(): void
    {
        // If profit 0.5% applied twice on BC rate 100: 100*0.995*0.995=99.0025
        // vs once: 99.5 — deviation from single application expected is small; guard uses peers.
        $once = '99.5';
        $twice = bcmul('100', bcmul('0.995', '0.995', 18), 18);
        $r = $this->guard->evaluate($twice, [$once], '0.03', clamp: false);
        // twice is only ~0.5% below once — should pass (below_min allowed)
        $this->assertTrue($r['ok']);
    }
}
