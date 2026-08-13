<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\DirectionExchange;
use App\Services\Rates\CanonicalDirectionRateCalculator;
use App\Services\Rates\RateChannel;
use App\Services\Rates\RateMode;
use App\Services\Rates\UsdtRubCommercialTargetSyncService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Live-DB: --sync-commercial derives Прибыль from BASE to hit policy ~95;
 * --apply still must not mutate Прибыль; legacy magic count stays 0.
 */
final class UsdtRubCommercialTargetSyncPersistenceContractTest extends TestCase
{
    public function test_sync_commercial_dry_run_then_apply_hits_target_and_is_idempotent(): void
    {
        $row = DB::selectOne(
            "SELECT de.id, de.course_value, de.profit
             FROM direction_exchange de
             JOIN currencies cb ON cb.id = de.id_currency1
             JOIN currencies cs ON cs.id = de.id_currency2
             WHERE de.deleted_at IS NULL
               AND cb.designation_xml = 'USDTTRC20'
               AND cs.designation_xml = 'SBERRUB'
               AND de.parser_source_name = 'DERIVED_MARKET_BASELINE'
             LIMIT 1"
        );
        if (!$row) {
            $this->markTestSkipped('missing USDTTRC20→SBERRUB DERIVED');
        }

        $code = Artisan::call('rates:apply-usdt-rub-commercial-target', [
            '--sync-commercial' => true,
            '--dry-run' => true,
            '--skip-xml-sync' => true,
            '--from' => 'USDTTRC20',
            '--to' => 'SBERRUB,TBRUB,TCSBRUB,SBPRUB,RFBRUB,ACRUB',
        ]);
        $this->assertSame(0, $code, Artisan::output());

        $code = Artisan::call('rates:apply-usdt-rub-commercial-target', [
            '--sync-commercial' => true,
            '--skip-xml-sync' => true,
            '--from' => 'USDTTRC20',
            '--to' => 'SBERRUB,TBRUB,TCSBRUB,SBPRUB,RFBRUB,ACRUB',
        ]);
        $this->assertSame(0, $code, Artisan::output());

        $dir = DirectionExchange::query()->with(['currency1', 'currency2'])->findOrFail((int) $row->id);
        $calc = CanonicalDirectionRateCalculator::make();
        $floating = $calc->calculate($dir, RateMode::Floating, RateChannel::Website);
        $this->assertTrue($floating->eligible);
        $this->assertTrue(abs((float) $floating->finalRate - 95.0) < 0.05, 'floating='.$floating->finalRate);
        $this->assertSame(0, UsdtRubCommercialTargetSyncService::legacyMagicProfitCount());

        $profitAfterFirst = (string) $dir->profit;

        $sync = \App\Services\Rates\UsdtRubCommercialTargetSyncService::make()->sync(
            dryRun: false,
            fromCodes: ['USDTTRC20'],
            toCodes: ['SBERRUB', 'TBRUB', 'TCSBRUB', 'SBPRUB', 'RFBRUB', 'ACRUB'],
        );
        $this->assertTrue($sync['ok'], json_encode($sync));
        $this->assertSame(0, (int) $sync['written'], 'idempotent second sync must not rewrite');
        $dir2 = DirectionExchange::query()->findOrFail((int) $row->id);
        $this->assertSame($profitAfterFirst, (string) $dir2->profit);
    }

    public function test_cardrub_not_forced_to_absolute_95(): void
    {
        $row = DB::selectOne(
            "SELECT de.id, de.profit, de.course_value
             FROM direction_exchange de
             JOIN currencies cb ON cb.id = de.id_currency1
             JOIN currencies cs ON cs.id = de.id_currency2
             WHERE de.deleted_at IS NULL
               AND cb.designation_xml = 'USDTTRC20'
               AND cs.designation_xml = 'CARDRUB'
             LIMIT 1"
        );
        if (!$row) {
            $this->markTestSkipped('missing CARDRUB');
        }
        $before = (string) $row->profit;

        Artisan::call('rates:apply-usdt-rub-commercial-target', [
            '--sync-commercial' => true,
            '--skip-xml-sync' => true,
            '--from' => 'USDTTRC20',
            '--to' => 'SBERRUB,CARDRUB',
        ]);

        $after = DirectionExchange::query()->findOrFail((int) $row->id);
        $this->assertSame($before, (string) $after->profit, 'CARDRUB profit must not be rewritten by classic absolute sync');
    }
}
