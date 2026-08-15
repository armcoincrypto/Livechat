<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Content\NewsUpdateParentUrl;
use PHPUnit\Framework\TestCase;

final class NewsUpdateParentUrlTest extends TestCase
{
    public function test_preserve_keeps_existing_slug_even_when_titles_would_drift(): void
    {
        $current = 'exswaping-prinimaet-samye-izvestnye-kriptovaliuty-dlia-obmena';
        $names = [
            'ru' => 'Exswaping: популярные криптовалюты для обмена',
            'en' => 'Exswaping: Supports the Most Popular Cryptocurrencies for Exchange',
            'zh' => 'Exswaping 支持最流行的加密货币兑换',
        ];

        $resolved = NewsUpdateParentUrl::resolve($current, $names, true);

        $this->assertSame($current, $resolved);
    }

    public function test_without_preserve_regenerates_from_first_name(): void
    {
        $names = [
            'ru' => 'Exswaping: популярные криптовалюты для обмена',
            'en' => 'English Title',
        ];

        $resolved = NewsUpdateParentUrl::resolve('old-slug', $names, false);

        $this->assertSame('exswaping-populiarnye-kriptovaliuty-dlia-obmena', $resolved);
    }

    public function test_preserve_flag_parsing(): void
    {
        $this->assertTrue(NewsUpdateParentUrl::preserveFlagFromRequest('1'));
        $this->assertTrue(NewsUpdateParentUrl::preserveFlagFromRequest('true'));
        $this->assertTrue(NewsUpdateParentUrl::preserveFlagFromRequest(true));
        $this->assertFalse(NewsUpdateParentUrl::preserveFlagFromRequest('0'));
        $this->assertFalse(NewsUpdateParentUrl::preserveFlagFromRequest(null));
        $this->assertFalse(NewsUpdateParentUrl::preserveFlagFromRequest(''));
    }
}
