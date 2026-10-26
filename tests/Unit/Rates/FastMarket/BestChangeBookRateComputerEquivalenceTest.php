<?php

declare(strict_types=1);

namespace Tests\Unit\Rates\FastMarket;

use App\Models\BestChangeDirection;
use App\Services\Rates\FastMarket\BestChangeBookRateComputer;
use App\Services\Rates\RateSanityGuard;
use App\Settings\BestChangeConfig;
use iEXPackages\BestChange\DTO\ComputedRate;
use iEXPackages\BestChange\DTO\PreparedMarket;
use iEXPackages\BestChange\DTO\RateSelectionPolicy;
use iEXPackages\BestChange\Services\BestChangeMarketPipeline;
use iEXPackages\BestChange\Services\RateCalculator;
use iEXPackages\BestChange\Services\RateSelectionPolicyResolver;
use PHPUnit\Framework\TestCase;

/**
 * Proves BookRateComputer delegates to RateCalculator for identical prepared
 * selection — same canonical engine, no duplicate formula.
 */
final class BestChangeBookRateComputerEquivalenceTest extends TestCase
{
    public function test_same_selected_row_matches_rate_calculator_output(): void
    {
        $direction = new BestChangeDirection();
        $direction->id = 1;
        $direction->id_direction_exchange = 10;
        $direction->formula_value = '';
        $direction->step = '0';

        $selected = ['changer' => 42, 'rate' => '2', 'rankrate' => '2'];
        $sorted = [$selected];
        $policy = new RateSelectionPolicy(
            typeField: 'rate',
            sortOrder: 'api',
            mode: 'position',
            topN: 1,
            positionWindowSeconds: 0,
        );

        $prepared = new PreparedMarket(
            rawRows: $sorted,
            filteredRows: $sorted,
            sortedRows: $sorted,
            rejectCounters: [],
            presence: null,
            selectedRow: $selected,
            skipReason: null,
            requestedPosition: 1,
        );

        $expected = new ComputedRate(
            rateValue: '0.5',
            rateValueWithoutStep: '0.5',
            sourceName: 'Ex42',
        );

        $pipeline = $this->createMock(BestChangeMarketPipeline::class);
        $pipeline->method('prepare')->willReturn($prepared);

        $calculator = $this->createMock(RateCalculator::class);
        $calculator->expects($this->once())
            ->method('calculate')
            ->with(
                $this->identicalTo($direction),
                $selected,
                $sorted,
                $this->isInstanceOf(RateSelectionPolicy::class),
                $this->equalTo(['USD' => '1']),
                $this->equalTo([42 => 'Ex42']),
            )
            ->willReturn($expected);

        $resolver = $this->createMock(RateSelectionPolicyResolver::class);
        $resolver->method('resolve')->willReturn($policy);

        $settings = $this->createMock(BestChangeConfig::class);
        $settings->method('position')->willReturn(1);
        $settings->method('blacklist')->willReturn([]);

        $sanity = $this->createMock(RateSanityGuard::class);
        $sanity->method('peerRatesFromBestChangeRows')->willReturn(['0.5']);
        $sanity->method('evaluate')->willReturn([
            'ok' => true,
            'action' => 'pass',
            'candidate' => '0.5',
            'baseline' => '0.5',
            'ratio' => '1',
            'effective' => '0.5',
            'reason' => null,
        ]);

        $computer = new BestChangeBookRateComputer(
            settings: $settings,
            policyResolver: $resolver,
            marketPipeline: $pipeline,
            calculator: $calculator,
            rateSanityGuard: $sanity,
        );

        $out = $computer->compute(
            direction: $direction,
            rawRows: $sorted,
            pairKey: '1-2',
            externalRates: ['USD' => '1'],
            exchangersMap: [42 => 'Ex42'],
        );

        $this->assertTrue($out['ok']);
        $this->assertSame('0.5', $out['rate_value']);
        $this->assertSame('Ex42', $out['source_name']);
        $this->assertSame(0, $this->algorithmMismatchCount([$expected->rateValue], [$out['rate_value']]));
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private function algorithmMismatchCount(array $a, array $b): int
    {
        $n = 0;
        foreach ($a as $i => $left) {
            $right = $b[$i] ?? '';
            if (bccomp((string) $left, (string) $right, 12) !== 0) {
                $n++;
            }
        }

        return $n;
    }
}
