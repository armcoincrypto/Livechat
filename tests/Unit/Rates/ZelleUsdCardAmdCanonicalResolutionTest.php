<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\CanonicalDirectionResolver;
use Tests\TestCase;

final class ZelleUsdCardAmdCanonicalResolutionTest extends TestCase
{
    public function test_zelleusd_cardamd_resolves_deterministically_to_allowlisted_direction(): void
    {
        CanonicalDirectionResolver::clearCaches();

        $a = CanonicalDirectionResolver::resolve('ZELLEUSD', 'CARDAMD', 'WEBSITE_DEEP_LINK');
        $b = CanonicalDirectionResolver::resolve('ZELLEUSD', 'CARDAMD', 'QUOTE');

        // Live public twin is EVOCA (52) direction 2111; stale Ameria 1558 is status=0.
        $this->assertContains($a['status'], [
            CanonicalDirectionResolver::STATUS_PAIR_ALIAS_RESOLVED,
            CanonicalDirectionResolver::STATUS_EXACT_PAIR_FOUND,
        ]);
        $this->assertSame(2111, $a['direction_id']);
        $this->assertSame($a['direction_id'], $b['direction_id']);
        $this->assertSame(87, $a['currency1_id']);
        $this->assertSame(52, $a['currency2_id']); // Evoca CARDAMD bank edge
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

        $this->assertGreaterThanOrEqual(1, $rows->count());
        $edgeKeys = $rows->map(fn ($r) => $r->id_currency1.':'.$r->id_currency2)->unique();
        $this->assertSame($rows->count(), $edgeKeys->count(), 'Each active ZELLEUSD→CARDAMD row must be a distinct currency-id edge');
    }
}
