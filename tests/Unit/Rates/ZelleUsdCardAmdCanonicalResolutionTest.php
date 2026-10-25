<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Models\DirectionExchange;
use App\Services\Rates\CanonicalDirectionResolver;
use Tests\TestCase;

final class ZelleUsdCardAmdCanonicalResolutionTest extends TestCase
{
    public function test_zelleusd_cardamd_allowlist_targets_live_evoca_direction(): void
    {
        $config = json_decode(
            (string) file_get_contents(dirname(__DIR__, 3) . '/resources/rates/bestchange-public-directions.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame(4, (int) $config['version']);
        $this->assertSame(
            2111,
            (int) $config['identities']['CARDAMD']['direction_ids_by_pair']['ZELLEUSD->CARDAMD']
        );
        $this->assertSame(
            [87, 52],
            $config['identities']['CARDAMD']['url_currency_ids_by_pair']['ZELLEUSD->CARDAMD']
        );
        $this->assertArrayNotHasKey(
            'GRAM->CARDAMD',
            $config['identities']['CARDAMD']['direction_ids_by_pair']
        );
        $this->assertArrayNotHasKey(
            'REVBUSD->CARDAMD',
            $config['identities']['CARDAMD']['direction_ids_by_pair']
        );
    }

    public function test_resolve_honors_allowlist_when_fixture_contains_direction_2111(): void
    {
        CanonicalDirectionResolver::clearCaches();

        if (!DirectionExchange::query()->whereKey(2111)->exists()) {
            $this->markTestSkipped('phpunit fixture DB lacks direction 2111 (production has status=1 EVOCA twin)');
        }

        $a = CanonicalDirectionResolver::resolve('ZELLEUSD', 'CARDAMD', 'WEBSITE_DEEP_LINK');
        $b = CanonicalDirectionResolver::resolve('ZELLEUSD', 'CARDAMD', 'QUOTE');

        $this->assertSame(2111, $a['direction_id']);
        $this->assertSame($a['direction_id'], $b['direction_id']);
        $this->assertSame(87, $a['currency1_id']);
        $this->assertSame(52, $a['currency2_id']);
    }

    public function test_active_zelleusd_cardamd_bank_edges_remain_distinct_currency_pairs(): void
    {
        $rows = \DB::table('direction_exchange as d')
            ->join('currencies as c1', 'c1.id', '=', 'd.id_currency1')
            ->join('currencies as c2', 'c2.id', '=', 'd.id_currency2')
            ->whereNull('d.deleted_at')
            ->where('d.status', 1)
            ->where('c1.designation_xml', 'ZELLEUSD')
            ->where('c2.designation_xml', 'CARDAMD')
            ->select(['d.id', 'd.id_currency1', 'd.id_currency2'])
            ->orderBy('d.id')
            ->get();

        if ($rows->isEmpty()) {
            $this->markTestSkipped('phpunit fixture has no active ZELLEUSD→CARDAMD edges');
        }

        $edgeKeys = $rows->map(fn ($r) => $r->id_currency1.':'.$r->id_currency2)->unique();
        $this->assertSame($rows->count(), $edgeKeys->count(), 'Each active ZELLEUSD→CARDAMD row must be a distinct currency-id edge');
    }
}
