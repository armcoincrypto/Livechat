<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\DirectionExchange;
use App\Services\Rates\UsdtRubCommercialTargetSyncService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Live-DB: MANUAL_PROFIT ownership — --sync-commercial must not rewrite Прибыль.
 */
final class UsdtRubCommercialTargetSyncPersistenceContractTest extends TestCase
{
    public function test_sync_commercial_refuses_to_rewrite_under_manual_profit(): void
    {
        $row = DB::selectOne(
            "SELECT de.id, de.course_value, de.profit
             FROM direction_exchange de
             JOIN currencies cb ON cb.id = de.id_currency1
             JOIN currencies cs ON cs.id = de.id_currency2
             WHERE de.deleted_at IS NULL
               AND cb.designation_xml = 'USDTTRC20'
               AND cs.designation_xml = 'SBERRUB'
             LIMIT 1"
        );
        if (!$row) {
            $this->markTestSkipped('missing USDTTRC20→SBERRUB');
        }

        $before = (string) $row->profit;

        $code = Artisan::call('rates:apply-usdt-rub-commercial-target', [
            '--sync-commercial' => true,
            '--dry-run' => true,
            '--skip-xml-sync' => true,
            '--from' => 'USDTTRC20',
            '--to' => 'SBERRUB,TBRUB,TCSBRUB,SBPRUB,RFBRUB,ACRUB',
        ]);
        $this->assertSame(0, $code, Artisan::output());

        $probe = UsdtRubCommercialTargetSyncService::make()->sync(dryRun: true);
        $this->assertSame('manual_profit_ownership', $probe['reason'] ?? null);
        $this->assertSame(0, (int) ($probe['written'] ?? -1));

        $code = Artisan::call('rates:apply-usdt-rub-commercial-target', [
            '--sync-commercial' => true,
            '--skip-xml-sync' => true,
            '--from' => 'USDTTRC20',
            '--to' => 'SBERRUB,TBRUB,TCSBRUB,SBPRUB,RFBRUB,ACRUB',
        ]);
        $this->assertSame(0, $code, Artisan::output());

        $after = DirectionExchange::query()->findOrFail((int) $row->id);
        $this->assertSame($before, (string) $after->profit, 'MANUAL_PROFIT must keep admin Прибыль');
        $this->assertSame(0, UsdtRubCommercialTargetSyncService::legacyMagicProfitCount());

        $sync = UsdtRubCommercialTargetSyncService::make()->sync(
            dryRun: false,
            fromCodes: ['USDTTRC20'],
            toCodes: ['SBERRUB', 'TBRUB', 'TCSBRUB', 'SBPRUB', 'RFBRUB', 'ACRUB'],
        );
        $this->assertTrue($sync['ok'], json_encode($sync));
        $this->assertSame('manual_profit_ownership', $sync['reason']);
        $this->assertSame(0, (int) $sync['written']);
    }

    public function test_cardrub_not_forced_by_classic_sync(): void
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
