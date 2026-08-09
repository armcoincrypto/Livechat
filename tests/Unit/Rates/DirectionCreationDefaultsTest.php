<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\DirectionCreationDefaults;
use PHPUnit\Framework\TestCase;

final class DirectionCreationDefaultsTest extends TestCase
{
    public function test_default_profit_and_gram_limits(): void
    {
        $d = DirectionCreationDefaults::fromStorageApp();
        $this->assertSame('1.5', $d->defaultProfitPercent());
        $gram = $d->giveLimitsForXml('GRAM');
        $this->assertSame('400', $gram['min']);
        $this->assertSame('2000', $gram['max']);
    }
}
