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

    public function test_tusdtrc20_and_dai_are_retired_usdttrc20_is_not(): void
    {
        $this->assertTrue(CurrencyPublicRetirement::isCurrencyIdRetired(43));
        $this->assertTrue(CurrencyPublicRetirement::isDesignationRetired('TUSDTRC20'));
        $this->assertTrue(CurrencyPublicRetirement::isCurrencyIdRetired(41));
        $this->assertTrue(CurrencyPublicRetirement::isDesignationRetired('DAI'));
        $this->assertFalse(CurrencyPublicRetirement::isCurrencyIdRetired(3));
        $this->assertFalse(CurrencyPublicRetirement::isDesignationRetired('USDTTRC20'));
        $this->assertFalse(CurrencyPublicRetirement::isDesignationRetired('ZELLEUSD'));
    }

    public function test_zelle_temp_exclusion_preserved_and_not_currency_retired(): void
    {
        $this->assertTrue(PublicDuplicateExclusion::isExcluded(1519));
        $this->assertFalse(CurrencyPublicRetirement::isDesignationRetired('ZELLEUSD'));
    }
}
