<?php

declare(strict_types=1);

namespace App\Services\Analytics;

/**
 * Feature flags for Batch 11 attribution dark-launch.
 * Default OFF — attribution must never be required for orders.
 */
final class AttributionFeatures
{
    public static function ingestEnabled(): bool
    {
        return filter_var(env('EXS_ATTRIBUTION_INGEST_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function orderLinkEnabled(): bool
    {
        return filter_var(env('EXS_ATTRIBUTION_ORDER_LINK_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function eventsEnabled(): bool
    {
        return filter_var(env('EXS_ATTRIBUTION_EVENTS_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function anyEnabled(): bool
    {
        return self::ingestEnabled() || self::orderLinkEnabled() || self::eventsEnabled();
    }
}
