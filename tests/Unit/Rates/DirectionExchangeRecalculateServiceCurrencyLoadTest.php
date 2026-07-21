<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;

final class DirectionExchangeRecalculateServiceCurrencyLoadTest extends TestCase
{
    public function testRecalculationLoadsCompleteCurrencyRowsForRubSourceSelection(): void
    {
        $root = dirname(__DIR__, 3);
        $source = (string) file_get_contents(
            $root . '/packages/BestChange/Services/DirectionExchangeRecalculateService.php',
        );

        self::assertStringContainsString("'currency1',", $source);
        self::assertStringContainsString("'currency2',", $source);
        self::assertStringNotContainsString("'currency1:id,", $source);
        self::assertStringNotContainsString("'currency2:id,", $source);
    }
}
