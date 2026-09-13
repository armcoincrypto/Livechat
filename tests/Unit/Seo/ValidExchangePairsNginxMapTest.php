<?php

declare(strict_types=1);

namespace Tests\Unit\Seo;

use App\Services\Seo\ValidExchangePairsNginxMap;
use PHPUnit\Framework\TestCase;

final class ValidExchangePairsNginxMapTest extends TestCase
{
    public function test_render_sorts_unique_pairs_and_includes_polygon_give(): void
    {
        $body = ValidExchangePairsNginxMap::render([
            ['USDTPOLYGON', 'TCSBRUB'],
            ['USDTPOLYGON', 'SBERRUB'],
            ['USDTPOLYGON', 'SBERRUB'],
            ['ZELLEUSD', 'USDTPOLYGON'],
            ['', 'SBERRUB'],
        ]);

        $this->assertStringContainsString('# generated-by: seo:generate-valid-exchange-pairs-map', $body);
        $this->assertStringContainsString('# keys: 3', $body);
        $this->assertStringContainsString('"USDTPOLYGON:SBERRUB" 1;', $body);
        $this->assertStringContainsString('"USDTPOLYGON:TCSBRUB" 1;', $body);
        $this->assertStringContainsString('"ZELLEUSD:USDTPOLYGON" 1;', $body);
        $this->assertLessThan(
            strpos($body, '"USDTPOLYGON:TCSBRUB"'),
            strpos($body, '"USDTPOLYGON:SBERRUB"')
        );
        $this->assertSame(1, substr_count($body, '"USDTPOLYGON:SBERRUB" 1;'));
        $this->assertStringNotContainsString('USDTMATIC', $body);
    }
}
