<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\CanonicalDirectionResolver;
use App\Services\Rates\PublicDuplicateExclusion;
use Tests\TestCase;

final class CanonicalDirectionResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PublicDuplicateExclusion::clearCache();
        CanonicalDirectionResolver::clearCaches();
    }

    public function test_invalid_codes_fail_closed(): void
    {
        $r = CanonicalDirectionResolver::resolve('USDTTRC20', '!!!', 'WEBSITE_DEEP_LINK');
        $this->assertSame(CanonicalDirectionResolver::STATUS_PAIR_INVALID, $r['status']);
        $this->assertNull($r['direction_id']);
    }

    public function test_aliases_include_ton_gram(): void
    {
        $this->assertSame(['TON', 'GRAM'], CanonicalDirectionResolver::aliasesFor('TON'));
        $this->assertSame(['GRAM', 'TON'], CanonicalDirectionResolver::aliasesFor('GRAM'));
    }

    public function test_cashusd_la_duplicate_exclusions_remain(): void
    {
        $this->assertTrue(PublicDuplicateExclusion::isExcluded(1487));
        $this->assertTrue(PublicDuplicateExclusion::isExcluded(1493));
        $this->assertFalse(PublicDuplicateExclusion::isExcluded(1263));
        $this->assertSame(1263, PublicDuplicateExclusion::canonicalReplacement(1487));
    }

    public function test_multi_edge_cardamd_resolves_via_allowlist_when_live_db_present(): void
    {
        if (!class_exists(\App\Models\DirectionExchange::class)) {
            $this->markTestSkipped('models unavailable');
        }

        try {
            $r = CanonicalDirectionResolver::resolve('BTC', 'CARDAMD', 'BESTCHANGE_LINK');
        } catch (\Throwable $e) {
            $this->markTestSkipped('db unavailable: '.$e->getMessage());
        }

        if ($r['status'] === CanonicalDirectionResolver::STATUS_PAIR_UNAVAILABLE) {
            $this->markTestSkipped('BTC→CARDAMD not quoteable in this environment');
        }

        $this->assertContains($r['status'], [
            CanonicalDirectionResolver::STATUS_EXACT_PAIR_FOUND,
            CanonicalDirectionResolver::STATUS_PAIR_ALIAS_RESOLVED,
        ]);
        $this->assertNotNull($r['direction_id']);
        // Allowlist claims BTC→CARDAMD → 2075 (Evoca).
        if ((int) $r['direction_id'] > 0) {
            $this->assertSame(2075, (int) $r['direction_id']);
        }
    }

    public function test_cashusd_la_only_edge_resolves_without_hard_am_filter_when_live_db_present(): void
    {
        try {
            $r = CanonicalDirectionResolver::resolve('BTC', 'CASHUSD', 'BESTCHANGE_LINK');
        } catch (\Throwable $e) {
            $this->markTestSkipped('db unavailable: '.$e->getMessage());
        }

        if ($r['status'] === CanonicalDirectionResolver::STATUS_PAIR_UNAVAILABLE) {
            $this->markTestSkipped('BTC→CASHUSD not quoteable in this environment');
        }

        $this->assertContains($r['status'], [
            CanonicalDirectionResolver::STATUS_EXACT_PAIR_FOUND,
            CanonicalDirectionResolver::STATUS_PAIR_ALIAS_RESOLVED,
        ]);
        $this->assertSame(1494, (int) $r['direction_id']);
    }
}
