<?php

declare(strict_types=1);

namespace Tests\Unit\Analytics;

use App\Services\Analytics\AttributionFunnelEventMap;
use PHPUnit\Framework\TestCase;

final class AttributionFunnelEventMapTest extends TestCase
{
    public function test_required_mappings(): void
    {
        self::assertSame('order_created', AttributionFunnelEventMap::eventForStatus(2));
        self::assertSame('payment_detected', AttributionFunnelEventMap::eventForStatus(7));
        self::assertSame('processing_started', AttributionFunnelEventMap::eventForStatus(3));
        self::assertSame('processing_started', AttributionFunnelEventMap::eventForStatus(16));
        self::assertSame('order_completed', AttributionFunnelEventMap::eventForStatus(4));
        self::assertSame('order_expired', AttributionFunnelEventMap::eventForStatus(1));
        self::assertSame('order_cancelled', AttributionFunnelEventMap::eventForStatus(6));
    }

    public function test_rejected_invalid_deleted_unmapped(): void
    {
        self::assertNull(AttributionFunnelEventMap::eventForStatus(5));
        self::assertNull(AttributionFunnelEventMap::eventForStatus(10));
        self::assertNull(AttributionFunnelEventMap::eventForStatus(11));
        self::assertSame([5, 10, 11], AttributionFunnelEventMap::UNMAPPED_TERMINAL);
    }
}
