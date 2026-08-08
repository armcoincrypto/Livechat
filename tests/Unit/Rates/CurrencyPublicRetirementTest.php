<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\CurrencyPublicRetirement;
use App\Services\Rates\PublicDuplicateExclusion;
use Tests\TestCase;

final class CurrencyPublicRetirementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CurrencyPublicRetirement::clearCache();
        PublicDuplicateExclusion::clearCache();
    }

    public function test_owner_retired_currencies_include_tusd_dai_ftn_a7a5(): void
    {
        $this->assertTrue(CurrencyPublicRetirement::isCurrencyIdRetired(43));
        $this->assertTrue(CurrencyPublicRetirement::isDesignationRetired('TUSDTRC20'));
        $this->assertTrue(CurrencyPublicRetirement::isCurrencyIdRetired(41));
        $this->assertTrue(CurrencyPublicRetirement::isDesignationRetired('DAI'));
        $this->assertTrue(CurrencyPublicRetirement::isCurrencyIdRetired(60));
        $this->assertTrue(CurrencyPublicRetirement::isDesignationRetired('FTN Bahamut'));
        $this->assertTrue(CurrencyPublicRetirement::isDesignationRetired('FTN'));
        $this->assertTrue(CurrencyPublicRetirement::isCurrencyIdRetired(73));
        $this->assertTrue(CurrencyPublicRetirement::isDesignationRetired('A7A5'));
        $this->assertFalse(CurrencyPublicRetirement::isCurrencyIdRetired(3));
        $this->assertFalse(CurrencyPublicRetirement::isDesignationRetired('USDTTRC20'));
        $this->assertFalse(CurrencyPublicRetirement::isDesignationRetired('ZELLEUSD'));
    }

    public function test_zelle_not_currency_retired_and_btc_public_not_excluded(): void
    {
        $this->assertFalse(CurrencyPublicRetirement::isDesignationRetired('ZELLEUSD'));
        $this->assertFalse(PublicDuplicateExclusion::isExcluded(1519));
        $this->assertTrue(PublicDuplicateExclusion::isExcluded(1971));
    }
}
