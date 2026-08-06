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

    /**
     * When true, only synthetic canary session ids (stagec_*) are accepted.
     * Used for Stage C privacy-safe ingestion without ordinary-visitor capture.
     */
    public static function canaryOnly(): bool
    {
        return filter_var(env('EXS_ATTRIBUTION_CANARY_ONLY', false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function canaryMaxRows(): int
    {
        $n = (int) env('EXS_ATTRIBUTION_CANARY_MAX_ROWS', 20);

        return max(1, min($n, 20));
    }

    public static function isCanarySessionId(?string $sessionId): bool
    {
        if (! is_string($sessionId) || $sessionId === '') {
            return false;
        }

        return (bool) preg_match('/^stagec_[A-Za-z0-9_-]{8,57}$/', $sessionId);
    }
}
