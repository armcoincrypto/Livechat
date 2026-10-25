<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;

/**
 * Ensures every public BestChange-owned customer path shares RateDirectionEligibility.
 */
final class BestChangePublicEntryPointsContractTest extends TestCase
{
    public function test_direction_detail_omits_course_when_quote_disallowed(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/packages/ExchangerClient/Http/Resources/Operations/DirectionDetailResource.php');
        $this->assertStringContainsString("if (!empty(\$surface['quote_allowed']))", $src);
        $this->assertStringContainsString('rate_unavailable', $src);
        $this->assertStringContainsString('ERROR_DIRECTION_TEMPORARILY_UNAVAILABLE', $src);
    }

    public function test_order_validate_blocks_when_order_disallowed(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/packages/Order/Concerns/ValidatesOrderRules.php');
        $this->assertStringContainsString("empty(\$surface['order_allowed'])", $src);
        $this->assertStringContainsString('Направление временно недоступно', $src);
    }

    public function test_tarif_skips_disallowed_directions(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/packages/ExchangerClient/Http/Controller/TarifController.php');
        $this->assertStringContainsString("empty(\$surface['quote_allowed'])", $src);
        $this->assertStringContainsString('continue;', $src);
    }
}
