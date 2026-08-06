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

    public function test_cashusd_la_directions_are_publicly_excluded(): void
    {
        $this->assertTrue(PublicDuplicateExclusion::isExcluded(1487));
        $this->assertTrue(PublicDuplicateExclusion::isExcluded(1493));
        $this->assertFalse(PublicDuplicateExclusion::isExcluded(1263));
        $this->assertSame(1263, PublicDuplicateExclusion::canonicalReplacement(1487));
    }
}
