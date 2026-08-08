<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\CanonicalDirectionEligibility;
use App\Services\Rates\PublicDuplicateExclusion;
use Tests\TestCase;

final class ActivePairClosureCatalogGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PublicDuplicateExclusion::clearCache();
    }

    public function test_exclusions_lists_permanent_and_retirement_targets(): void
    {
        $path = base_path('resources/rates/public-duplicate-exclusions.json');
        $json = json_decode((string) file_get_contents($path), true);
        $this->assertGreaterThanOrEqual(9, (int) ($json['version'] ?? 0));
        $ids = array_map('intval', $json['exclude_direction_ids'] ?? []);

        foreach ([1487, 1488, 1491, 1492, 1493, 1971] as $id) {
            $this->assertContains($id, $ids, "permanent exclusion missing {$id}");
        }
        $this->assertNotContains(1519, $ids, 'ZELLEUSD→BTC 1519 must remain public after restore');
        $this->assertNotContains(1540, $ids, 'ZELLEUSD→SBERRUB 1540 must remain public');
        $this->assertContains(1850, $ids, '1850 belt-and-suspenders exclusion required');
        $this->assertSame(43, (int) ($json['currency_retirement']['TUSDTRC20'] ?? 0));
        $this->assertSame(41, (int) ($json['currency_retirement']['DAI'] ?? 0));
        $this->assertSame(60, (int) ($json['currency_retirement']['FTN_Bahamut'] ?? 0));
        $this->assertSame(73, (int) ($json['currency_retirement']['A7A5'] ?? 0));
        $this->assertSame(3, (int) ($json['currency_retirement']['USDTTRC20_unaffected'] ?? 0));
    }


    public function test_cashusd_la_inbound_excluded_am_canonical_replacement(): void
    {
        $this->assertTrue(PublicDuplicateExclusion::isExcluded(1487));
        $this->assertSame(1263, PublicDuplicateExclusion::canonicalReplacement(1487));
        $this->assertFalse(PublicDuplicateExclusion::isExcluded(1263));
    }

    public function test_catalog_prefilter_rejects_excluded_and_zero_course(): void
    {
        $this->assertFalse(CanonicalDirectionEligibility::passesPublicCatalogPrefilter(1487, '1.23'));
        $this->assertFalse(CanonicalDirectionEligibility::passesPublicCatalogPrefilter(1971, '1.23'));
        $this->assertFalse(CanonicalDirectionEligibility::passesPublicCatalogPrefilter(999001, '0'));
        $this->assertFalse(CanonicalDirectionEligibility::passesPublicCatalogPrefilter(999001, '0.0'));
        $this->assertFalse(CanonicalDirectionEligibility::passesPublicCatalogPrefilter(999001, ''));
        $this->assertTrue(CanonicalDirectionEligibility::passesPublicCatalogPrefilter(999001, '1.5'));
        $this->assertTrue(CanonicalDirectionEligibility::passesPublicCatalogPrefilter(1519, '0.000015'));
    }

    public function test_legitimate_am_cashusd_canonical_not_excluded(): void
    {
        // Currency AM canonical direction ids from policy notes (1263 etc.)
        $this->assertFalse(PublicDuplicateExclusion::isExcluded(1263));
        $this->assertFalse(PublicDuplicateExclusion::isExcluded(1267));
        $this->assertTrue(CanonicalDirectionEligibility::passesPublicCatalogPrefilter(1263, '42.5'));
    }
}
