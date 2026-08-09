<?php

declare(strict_types=1);

namespace Tests\Unit\Courses\Export;

use App\Models\Currency;
use App\Models\DirectionExchange;
use App\Models\ExportRatesFile;
use iEXPackages\Courses\Export\Formats\BestChangeXMLExportFormat;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

/**
 * BestChange.ru cabinet reads legacy minamount/maxamount.
 * Schema 1.1 frommin/frommax alone surfaces as «нет мин./макс. суммы».
 */
final class BestChangeXmlMinAmountDualEmitTest extends TestCase
{
    public function test_build_item_emits_legacy_minamount_and_maxamount(): void
    {
        $format = new BestChangeXMLExportFormat();
        $method = new ReflectionMethod(BestChangeXMLExportFormat::class, 'buildItemData');
        $method->setAccessible(true);

        $currencyIn = new Currency([
            'designation_xml' => 'LTC',
            'number_format' => 8,
        ]);
        $currencyOut = new Currency([
            'designation_xml' => 'BTC',
            'number_format' => 8,
        ]);

        $rate = new DirectionExchange([
            'min_price1' => '8.634',
            'max_price1' => '64.753',
            'export_label_param' => 'manual',
            'type_reserve' => 1,
            'direction_reserve' => '100',
            'oth_comm_percent' => 0,
            'pay_comm_percent' => 0,
            'oth_comm_currency' => 0,
            'pay_comm_currency' => 0,
            'oth_comm2_percent' => 0,
            'pay_comm2_percent' => 0,
            'oth_comm2_currency' => 0,
            'pay_comm2_currency' => 0,
            'label_floating_minutes' => 0,
            'label_floating_percent' => 0,
            'label_delay' => 0,
        ]);
        $rate->setRelation('direction_exchange_percentage_amount', new Collection());

        $config = new ExportRatesFile([
            'in_type_fromfee' => 0,
            'in_type_tofee' => 0,
        ]);

        $item = $method->invoke(
            $format,
            $rate,
            $currencyIn,
            $currencyOut,
            ['in' => '10', 'out' => '1'],
            $config
        );

        $this->assertArrayHasKey('frommin', $item);
        $this->assertArrayHasKey('frommax', $item);
        $this->assertArrayHasKey('minamount', $item);
        $this->assertArrayHasKey('maxamount', $item);
        $this->assertSame($item['frommin'], $item['minamount']);
        $this->assertSame($item['frommax'], $item['maxamount']);
        $this->assertGreaterThan(0, (float) $item['minamount']);
        $this->assertTrue((float) $item['maxamount'] >= (float) $item['minamount']);
        $this->assertSame('true', $item['manual'] ?? null);

        // Legacy fields must appear after boolean params in array order (XSD legacy group).
        $keys = array_keys($item);
        $this->assertGreaterThan(
            array_search('manual', $keys, true),
            array_search('minamount', $keys, true)
        );
    }

    public function test_live_currencies_xml_has_zero_missing_legacy_limits(): void
    {
        $path = public_path('static/exports/currencies.xml');
        if (!is_file($path)) {
            $this->markTestSkipped('currencies.xml not present');
        }

        $xml = (string) file_get_contents($path);
        preg_match_all('#<item>(.*?)</item>#s', $xml, $matches);
        $this->assertNotEmpty($matches[1]);

        $missingMin = 0;
        $missingMax = 0;
        $badRange = 0;
        $emptyManual = 0;
        foreach ($matches[1] as $block) {
            if (!preg_match('#<minamount>([^<]+)</minamount>#', $block, $mn) || (float) $mn[1] <= 0) {
                $missingMin++;
            }
            if (!preg_match('#<maxamount>([^<]+)</maxamount>#', $block, $mx) || (float) $mx[1] <= 0) {
                $missingMax++;
            }
            if (isset($mn[1], $mx[1]) && (float) $mx[1] > 0 && (float) $mn[1] > (float) $mx[1]) {
                $badRange++;
            }
            if (str_contains($block, '<manual></manual>')) {
                $emptyManual++;
            }
            $this->assertMatchesRegularExpression('#<frommin>[0-9.]+</frommin>#', $block);
            $this->assertMatchesRegularExpression('#<frommax>[0-9.]+</frommax>#', $block);
        }

        $this->assertSame(0, $missingMin, 'exportable XML must not miss minamount');
        $this->assertSame(0, $missingMax, 'exportable XML must not miss maxamount');
        $this->assertSame(0, $badRange, 'from-side max must be >= min');
        $this->assertSame(0, $emptyManual, 'boolean manual must not be empty');
    }
}
