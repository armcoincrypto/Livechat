<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\DerivedMarketBaselineAuthority;
use App\Services\Rates\ZelleOutgoingRateWriteGuard;
use App\Services\Rates\ZelleUsdBenchmarkResolver;
use App\Services\Rates\ZelleUsdUsdtBenchmarkAuthority;
use Tests\TestCase;

final class ZelleOutgoingRateWriteGuardTest extends TestCase
{
    public function test_guard_constants_align_with_zelle_resolver(): void
    {
        $this->assertSame(
            ZelleUsdBenchmarkResolver::ZELLE_CURRENCY_ID,
            ZelleOutgoingRateWriteGuard::zelleCurrencyId()
        );
        $this->assertSame(
            ZelleUsdUsdtBenchmarkAuthority::PARSER_SOURCE_NAME,
            ZelleOutgoingRateWriteGuard::AUTHORITY
        );
    }

    public function test_deny_generic_write_blocks_known_zelle_outgoing_ids(): void
    {
        // Live production ZELLEUSD→CARDIDR conflict id from audit
        $deny = ZelleOutgoingRateWriteGuard::denyGenericWrite(1904, 'unit-test');
        if (!ZelleOutgoingRateWriteGuard::isZelleOutgoingId(1904)) {
            $this->markTestSkipped('direction 1904 not present in this DB');
        }
        $this->assertTrue($deny['blocked']);
        $this->assertStringContainsString('zelle_outgoing_owned_by_', (string) $deny['reason']);
    }

    public function test_derived_authority_skips_zelle_outgoing_without_writing(): void
    {
        if (!ZelleOutgoingRateWriteGuard::isZelleOutgoingId(1962)) {
            $this->markTestSkipped('direction 1962 not present in this DB');
        }

        $before = \DB::table('direction_exchange')->where('id', 1962)->first([
            'course_value', 'parser_source_name', 'updated_at',
        ]);
        $this->assertNotNull($before);

        $auth = DerivedMarketBaselineAuthority::fromStorageApp();
        $result = $auth->apply(1962, dryRun: false);

        $this->assertTrue($result['ok'] ?? false);
        $this->assertSame(
            'zelle_outgoing_owned_by_'.ZelleUsdUsdtBenchmarkAuthority::PARSER_SOURCE_NAME,
            $result['skipped'] ?? null
        );

        $after = \DB::table('direction_exchange')->where('id', 1962)->first([
            'course_value', 'parser_source_name', 'updated_at',
        ]);
        $this->assertSame((string) $before->course_value, (string) $after->course_value);
        $this->assertSame((string) $before->parser_source_name, (string) $after->parser_source_name);
        $this->assertSame((string) $before->updated_at, (string) $after->updated_at);
    }

    public function test_non_zelle_direction_is_not_blocked_by_guard(): void
    {
        $deny = ZelleOutgoingRateWriteGuard::denyGenericWrite(1, 'unit-test');
        // id 1 may or may not exist; guard only blocks ZELLE currency1=87
        if (ZelleOutgoingRateWriteGuard::isZelleOutgoingId(1)) {
            $this->assertTrue($deny['blocked']);
        } else {
            $this->assertFalse($deny['blocked']);
        }
    }
}
