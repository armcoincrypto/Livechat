<?php

declare(strict_types=1);

namespace Tests\Unit\Seo;

use App\Console\Commands\GenerateValidExchangePairsNginxMapCommand;
use Tests\TestCase;

final class GenerateValidExchangePairsNginxMapCommandTest extends TestCase
{
    public function test_quoteable_loader_includes_polygon_give_and_excludes_archive(): void
    {
        $cmd = app(GenerateValidExchangePairsNginxMapCommand::class);
        $pairs = $cmd->loadQuoteablePairs();
        $keys = array_map(static fn (array $pair): string => $pair[0] . ':' . $pair[1], $pairs);

        $this->assertContains('USDTPOLYGON:SBERRUB', $keys);
        $this->assertContains('USDTPOLYGON:TCSBRUB', $keys);
        $this->assertNotContains('USDTMATIC:SBERRUB', $keys);

        $give = array_values(array_filter($pairs, static fn (array $pair): bool => $pair[0] === 'USDTPOLYGON'));
        if (count($pairs) < 50) {
            $this->markTestSkipped('phpunit DB quoteable pair corpus too sparse for production count gate');
        }
        $this->assertGreaterThanOrEqual(77, count($give));
    }
}
