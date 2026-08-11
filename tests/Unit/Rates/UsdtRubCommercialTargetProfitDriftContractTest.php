<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\DirectionExchange;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Live-DB contract: commercial-target --apply must not drift owner Прибыль.
 *
 * Requires production-like DB (same as other Exswaping rate contract tests).
 * Skips cleanly when representative directions are absent.
 */
final class UsdtRubCommercialTargetProfitDriftContractTest extends TestCase
{
    /** @var list<array{from:string,to:string}> */
    private const PAIRS = [
        ['from' => 'USDTTRC20', 'to' => 'SBERRUB'],
        ['from' => 'USDTTRC20', 'to' => 'SBPRUB'],
        ['from' => 'USDTBEP20', 'to' => 'ACRUB'],
        ['from' => 'USDTSOL', 'to' => 'TCSBRUB'],
        ['from' => 'USDTTON', 'to' => 'TBRUB'],
    ];

    public function test_apply_does_not_mutate_owner_profit_on_representative_pairs(): void
    {
        $ids = [];
        foreach (self::PAIRS as $pair) {
            $row = DB::selectOne(
                'SELECT de.id, de.profit, de.floating_fee, de.fix_fee
                 FROM direction_exchange de
                 JOIN currencies cb ON cb.id = de.id_currency1
                 JOIN currencies cs ON cs.id = de.id_currency2
                 WHERE de.deleted_at IS NULL
                   AND cb.designation_xml = ?
                   AND cs.designation_xml = ?
                 LIMIT 1',
                [$pair['from'], $pair['to']]
            );
            if (!$row) {
                $this->markTestSkipped('missing '.$pair['from'].'→'.$pair['to']);
            }
            $ids[] = [
                'id' => (int) $row->id,
                'profit' => (string) $row->profit,
                'floating_fee' => (string) $row->floating_fee,
                'fix_fee' => (string) $row->fix_fee,
                'from' => $pair['from'],
                'to' => $pair['to'],
            ];
        }

        // Refuse accidental legacy bootstrap.
        putenv('EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP');
        unset($_ENV['EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP'], $_SERVER['EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP']);

        $code = Artisan::call('rates:apply-usdt-rub-commercial-target', [
            '--apply' => true,
            '--target' => 95,
            '--from' => 'USDTTRC20,USDTBEP20,USDTSOL,USDTTON',
            '--to' => 'SBERRUB,SBPRUB,ACRUB,TCSBRUB,TBRUB',
            '--skip-xml-sync' => true,
        ]);
        $this->assertSame(0, $code, Artisan::output());

        foreach ($ids as $expect) {
            $dir = DirectionExchange::query()->findOrFail($expect['id']);
            $this->assertSame(
                $expect['profit'],
                (string) $dir->profit,
                "profit drift on {$expect['from']}→{$expect['to']} id={$expect['id']}"
            );
            $this->assertSame(
                $expect['floating_fee'],
                (string) $dir->floating_fee,
                "floating_fee drift on {$expect['from']}→{$expect['to']}"
            );
            $this->assertSame(
                $expect['fix_fee'],
                (string) $dir->fix_fee,
                "fix_fee drift on {$expect['from']}→{$expect['to']}"
            );
        }
    }

    public function test_legacy_flag_without_env_is_refused(): void
    {
        putenv('EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP');
        unset($_ENV['EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP'], $_SERVER['EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP']);

        $code = Artisan::call('rates:apply-usdt-rub-commercial-target', [
            '--apply' => true,
            '--legacy-overwrite-owner-profit' => true,
        ]);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('legacy_profit_bootstrap_refused', Artisan::output());
    }
}
