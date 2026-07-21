<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\Currency;
use App\Models\DirectionExchange;
use App\Services\Rates\IndependentMarketBaseline;
use App\Services\Rates\RubFamilyPremiumPolicy;
use iEXPackages\Calculator\Strategies\IndependentRubStrategy;
use PHPUnit\Framework\TestCase;

final class IndependentRubCanonicalSourceTest extends TestCase
{
    public function testIndependentStablecoinBaselineReceivesApprovedPremiumOnce(): void
    {
        $strategy = new IndependentRubStrategy(
            $this->direction('USDTTRC20', 'SBERRUB'),
            new IndependentMarketBaseline([
                'USDRUB' => [
                    'rate' => '100',
                    'source' => 'russiancentralbank',
                    'as_of' => gmdate('c'),
                ],
            ], allowDatabase: false),
            $this->policy(),
        );

        self::assertSame('105.000000000000000000', $strategy->getRate());
    }

    public function testBestChangeCannotSubstituteForMissingIndependentBaseline(): void
    {
        $direction = $this->direction('BTC', 'SBERRUB');
        $direction->setAttribute('parser_source_name', 'BestChange');

        $strategy = new IndependentRubStrategy(
            $direction,
            new IndependentMarketBaseline([], allowDatabase: false),
            $this->policy(),
        );

        self::assertSame('0', $strategy->getRate());
    }

    public function testStaleIndependentProviderFailsClosed(): void
    {
        $strategy = new IndependentRubStrategy(
            $this->direction('BTC', 'SBERRUB'),
            new IndependentMarketBaseline([
                'BTCUSDT' => [
                    'rate' => '60000',
                    'source' => 'binance',
                    'as_of' => gmdate('c', time() - 901),
                ],
                'USDRUB' => [
                    'rate' => '100',
                    'source' => 'russiancentralbank',
                    'as_of' => gmdate('c'),
                ],
            ], allowDatabase: false),
            $this->policy(),
        );

        self::assertSame('0', $strategy->getRate());
    }

    private function direction(string $from, string $to): DirectionExchange
    {
        $direction = new DirectionExchange();
        $direction->setRelation('currency1', (new Currency())->forceFill(['designation_xml' => $from]));
        $direction->setRelation('currency2', (new Currency())->forceFill(['designation_xml' => $to]));

        return $direction;
    }

    private function policy(): RubFamilyPremiumPolicy
    {
        return new RubFamilyPremiumPolicy([
            'approved' => true,
            'default_thresholds' => [
                'unexplained_warning_percent' => 1,
                'unexplained_block_percent' => 2,
            ],
            'families' => [
                'SBERRUB' => [
                    'destination_codes' => ['SBERRUB'],
                    'decision' => 'APPROVE',
                    'target_premium_max_percent' => 5,
                    'warning_premium_percent' => 6,
                    'hard_maximum_premium_percent' => 8,
                    'order_allowed_when_approved' => true,
                    'export_allowed_when_approved' => true,
                ],
            ],
        ]);
    }
}
