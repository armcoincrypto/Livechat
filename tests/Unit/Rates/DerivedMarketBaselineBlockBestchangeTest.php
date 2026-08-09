<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\DerivedMarketBaselineAuthority;
use Tests\TestCase;

/**
 * GRAM/TON derived pairs must not be skipped or overwritten when a live
 * BestChange link exists — BC historically poisoned GRAM→USDT (~2× under).
 */
final class DerivedMarketBaselineBlockBestchangeTest extends TestCase
{
    public function test_config_blocks_bestchange_on_gram_usdt_and_trx_gram(): void
    {
        $cfg = DerivedMarketBaselineAuthority::fromStorageApp()->config()['directions'] ?? [];

        foreach ([11, 2977, 421] as $id) {
            $this->assertArrayHasKey((string) $id, $cfg, "direction {$id} must be derived-owned");
            $this->assertTrue(
                !empty($cfg[(string) $id]['ownership']['block_bestchange_overwrite']),
                "direction {$id} must set block_bestchange_overwrite"
            );
        }

        // Poisoned peer direction_course:11 must not remain as a leg source.
        foreach ($cfg as $dirCfg) {
            foreach (['asset_leg', 'fiat_leg'] as $leg) {
                if (($dirCfg[$leg]['source'] ?? '') !== 'direction_course') {
                    continue;
                }
                $this->assertNotSame(
                    11,
                    (int) ($dirCfg[$leg]['direction_id'] ?? 0),
                    'direction_course must not peer poisoned GRAM→USDTTRC20 id 11'
                );
            }
        }
    }

    public function test_apply_writes_despite_active_bestchange_when_blocked(): void
    {
        $auth = DerivedMarketBaselineAuthority::fromStorageApp();
        if (!$auth->owns(2977)) {
            $this->markTestSkipped('direction 2977 not in derived config');
        }

        $row = \DB::table('direction_exchange')->where('id', 2977)->first();
        if ($row === null) {
            $this->markTestSkipped('direction 2977 missing in DB');
        }

        // Ensure an active BC link exists so the old skip path would fire.
        $existing = \DB::table('bestchange_directions')
            ->where('id_direction_exchange', 2977)
            ->first();
        if ($existing === null) {
            \DB::table('bestchange_directions')->insert([
                'id_direction_exchange' => 2977,
                'status' => 1,
                'is_error_parser' => 0,
                'rate_value' => '9.999',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            \DB::table('bestchange_directions')
                ->where('id', $existing->id)
                ->update([
                    'status' => 1,
                    'is_error_parser' => 0,
                    'rate_value' => '9.999',
                    'updated_at' => now(),
                ]);
        }

        $eval = $auth->evaluate(2977);
        $this->assertTrue($eval['ok'] ?? false, (string) ($eval['reason'] ?? 'evaluate failed'));
        $this->assertNotNull($eval['rate']);

        $result = $auth->apply(2977, dryRun: false);
        $this->assertArrayNotHasKey('skipped', $result);

        $after = \DB::table('direction_exchange')->where('id', 2977)->first();
        $this->assertSame('DERIVED_MARKET_BASELINE', (string) $after->parser_source_name);
        $this->assertEqualsWithDelta((float) $eval['rate'], (float) $after->course_value, 1e-9);

        $activeBc = \DB::table('bestchange_directions')
            ->where('id_direction_exchange', 2977)
            ->where('status', 1)
            ->count();
        $this->assertSame(0, $activeBc, 'block_bestchange_overwrite must disable active BC links');
    }
}
