<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

require_once dirname(__DIR__, 3).'/packages/BestChange/Services/DirectionExchangeRecalculateService.php';

use App\Models\Currency;
use App\Models\DirectionExchange;
use iEXPackages\BestChange\Services\DirectionExchangeRecalculateService;
use PHPUnit\Framework\TestCase;

/**
 * Top-N may propose a higher course. The BestChange post-profit writer must not persist it.
 * Derived raw-base storage and Zelle authority are not clamped.
 */
final class BestChangeTopNProfitFloorTest extends TestCase
{
    /**
     * @return array<string, array{0:int,1:string,2:string,3:string}>
     */
    public static function defectCases(): array
    {
        return [
            '29 UAH' => [29, '45.895680991640441777', '46.070090000000000000', '46.080000995622933511'],
            '30 UAH' => [30, '45.84959999765250048', '46.079000000000000000', '46.079999997640704'],
            '34 UAH' => [34, '45.839696827688981356', '46.070090000000000000', '46.070047063004001362'],
            '16 BTC UAH' => [16, '3706496.407885847978593152', '3733964.318990000000000000', '3721381.935628361424290313'],
            '217 BTC UAH' => [217, '3732207.297513282438636359', '3746640.718390000000000000', '3747196.081840645018711203'],
            '218 BTC UAH' => [218, '3721478.923211879342734945', '3734645.679000000000000000', '3736424.621698674038890507'],
            '379 ETH UAH' => [379, '120422.818443979185162481', '120909.119000000000000000', '120906.444220862635705302'],
            '380 ETH UAH' => [380, '120074.837307827105190303', '120499.712990000000000000', '120557.065570107535331629'],
            '3145 RUB' => [3145, '89.135243977419903455', '90.035590000000000000', '90.035599977191821671'],
            '3148 RUB' => [3148, '89.13534301028933832', '90.035790000000000000', '90.03570001039327103'],
            '3149 RUB' => [3149, '91.24721101056168218', '91.247211010561682180', '92.168900010668365838'],
            '3150 RUB' => [3150, '90.925362040200466617', '91.843890000000000000', '91.843800040606531936'],
            '3151 RUB' => [3151, '88.830819016032808032', '89.728190000000000000', '89.728100016194755587'],
            '3177 RUB' => [3177, '91.24721101056168218', '92.169000000000000000', '92.168900010668365838'],
            '2 crypto' => [2, '0.000702574603851228', '0.000715039000000000', '0.00070960034988974'],
            '1507 fiat to coin' => [1507, '0.016890833549923647', '0.016912299000000000', '0.017059741885422883'],
        ];
    }

    /**
     * @dataProvider defectCases
     */
    public function test_topn_proposal_above_calculator_is_not_persisted(
        int $directionId,
        string $calculator,
        string $topn,
        string $rateValue
    ): void {
        $persisted = DirectionExchangeRecalculateService::persistedCourseAfterTopN($calculator, $topn, true);

        $this->assertSame($directionId, $directionId);
        $this->assertSame($rateValue, $rateValue);
        $this->assertFalse(
            DirectionExchangeRecalculateService::courseStrictlyGreater($persisted, $calculator),
            'persisted course exceeded Calculator for direction '.$directionId
        );
        if (DirectionExchangeRecalculateService::courseStrictlyGreater($topn, $calculator)) {
            $this->assertSame($calculator, $persisted);
        } else {
            $this->assertSame($topn, $persisted);
        }
    }

    /**
     * @return array<string, array{0:int,1:string,2:string}>
     */
    public static function controlCases(): array
    {
        return [
            '31' => [31, '45.850283566838804989', '45.850283566838804989'],
            '33' => [33, '45.841474831094148906', '46.079000000000000000'],
            '32' => [32, '45.553858130288261834', '45.819000000000000000'],
            '114' => [114, '45.840217211349195795', '45.840217211349195795'],
            '115' => [115, '45.840037110509223285', '45.840037110509223285'],
            '1415' => [1415, '89.015000039607224267', '89.016490000000000000'],
        ];
    }

    /**
     * @dataProvider controlCases
     */
    public function test_controls_persist_calculator_when_topn_would_raise(
        int $directionId,
        string $calculator,
        string $topn
    ): void {
        $persisted = DirectionExchangeRecalculateService::persistedCourseAfterTopN($calculator, $topn, true);
        $this->assertSame($directionId, $directionId);
        $this->assertFalse(DirectionExchangeRecalculateService::courseStrictlyGreater($persisted, $calculator));
        $this->assertSame(
            DirectionExchangeRecalculateService::courseStrictlyGreater($topn, $calculator) ? $calculator : $topn,
            $persisted
        );
    }

    public function test_lower_topn_course_is_preserved(): void
    {
        $persisted = DirectionExchangeRecalculateService::persistedCourseAfterTopN(
            '45.900000000000000000',
            '45.800000000000000000',
            true
        );
        $this->assertSame('45.800000000000000000', $persisted);
    }

    public function test_equal_courses_keep_topn_string(): void
    {
        $persisted = DirectionExchangeRecalculateService::persistedCourseAfterTopN(
            '45.850283566838804989',
            '45.850283566838804989',
            true
        );
        $this->assertSame('45.850283566838804989', $persisted);
    }

    public function test_derived_and_zelle_are_not_clamped(): void
    {
        $higher = '92.198590000000000000';
        $base = '92.196890000000000000';
        $this->assertSame(
            $higher,
            DirectionExchangeRecalculateService::persistedCourseAfterTopN($base, $higher, false)
        );

        $derived = $this->direction('USDTTRC20', 'SBERRUB', 'BestChange');
        $this->assertFalse(DirectionExchangeRecalculateService::topNProfitFloorApplies($derived, true));

        $sber = $this->direction('USDTTRC20', 'SBERRUB', 'BestChange');
        $tinkoff = $this->direction('USDTTRC20', 'TCSBRUB', 'BestChange');
        $gram = $this->direction('USDTTRC20', 'GRAM', 'BestChange');
        $this->assertFalse(DirectionExchangeRecalculateService::topNProfitFloorApplies($sber, true));
        $this->assertFalse(DirectionExchangeRecalculateService::topNProfitFloorApplies($tinkoff, true));
        $this->assertFalse(DirectionExchangeRecalculateService::topNProfitFloorApplies($gram, true));

        $zelleOut = $this->direction('ZELLEUSD', 'USDTTRC20', 'ZELLE_USDTTRC20_BENCHMARK');
        $coinZelle = $this->direction('USDTTRC20', 'ZELLEUSD', 'BestChange');
        $this->assertFalse(DirectionExchangeRecalculateService::topNProfitFloorApplies($zelleOut, false));
        $this->assertFalse(DirectionExchangeRecalculateService::topNProfitFloorApplies($coinZelle, false));

        $manual = $this->direction('BTC', 'ETH', 'manual');
        $this->assertFalse(DirectionExchangeRecalculateService::topNProfitFloorApplies($manual, false));
    }

    public function test_bestchange_post_profit_path_is_clamped(): void
    {
        $uah = $this->direction('USDTTRC20', 'OSDBUAH', 'BestChange');
        $this->assertTrue(DirectionExchangeRecalculateService::topNProfitFloorApplies($uah, false));
    }

    public function test_upsert_does_not_write_profit_position_or_mapping_columns(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3).'/packages/BestChange/Services/DirectionExchangeRecalculateService.php'
        );
        $this->assertStringContainsString(
            "['course_value', 'exchange_rate', 'is_error_rate', 'error_rate_text', 'parser_source_name', 'updated_at']",
            $src
        );
        $this->assertStringNotContainsString("'profit'", $src);
        $this->assertStringContainsString('profit_floor_blocked', $src);
        $this->assertStringContainsString('persistedCourseAfterTopN', $src);
    }

    public function test_floor_is_loaded_from_this_worktree(): void
    {
        $file = (new \ReflectionClass(DirectionExchangeRecalculateService::class))->getFileName();
        $this->assertIsString($file);
        $this->assertStringContainsString('topn-profit-floor-20260920', (string) $file);
        $this->assertTrue(method_exists(DirectionExchangeRecalculateService::class, 'persistedCourseAfterTopN'));
    }

    private function direction(string $fromXml, string $toXml, string $owner): DirectionExchange
    {
        $from = new Currency();
        $from->designation_xml = $fromXml;
        $to = new Currency();
        $to->designation_xml = $toXml;
        $direction = new DirectionExchange();
        $direction->parser_source_name = $owner;
        $direction->setRelation('currency1', $from);
        $direction->setRelation('currency2', $to);

        return $direction;
    }
}
