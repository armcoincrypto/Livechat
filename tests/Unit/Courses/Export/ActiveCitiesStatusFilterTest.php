<?php

namespace Tests\Unit\Courses\Export;

use App\Models\DirectionExchange;
use Illuminate\Support\Collection;
use iEXPackages\Courses\Export\Formats\BestChangeXMLExportFormat;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use stdClass;

final class ActiveCitiesStatusFilterTest extends TestCase
{
    public function test_eager_loaded_cities_exclude_inactive_status(): void
    {
        $active = new stdClass();
        $active->status = 1;
        $active->id = 18;

        $inactive = new stdClass();
        $inactive->status = 0;
        $inactive->id = 33;

        $rate = $this->getMockBuilder(DirectionExchange::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['relationLoaded'])
            ->getMock();
        $rate->method('relationLoaded')->with('direction_exchange_cities')->willReturn(true);
        $rate->direction_exchange_cities = collect([$active, $inactive]);

        $format = new BestChangeXMLExportFormat();
        $method = new ReflectionMethod(BestChangeXMLExportFormat::class, 'getActiveCities');
        $method->setAccessible(true);

        /** @var Collection $cities */
        $cities = $method->invoke($format, $rate);

        $this->assertCount(1, $cities);
        $this->assertSame(18, $cities->first()->id);
    }
}
