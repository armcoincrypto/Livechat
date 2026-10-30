<?php

declare(strict_types=1);

namespace App\Services\Analytics;

/**
 * Feature flags for Batch 11 attribution dark-launch.
 * Default OFF — attribution must never be required for orders.
 *
 * CC1: values come from config/attribution.php (env read at config build).
 * Do NOT call env() here — that silently defaults under config:cache.
 */
final class AttributionFeatures
{
    public static function ingestEnabled(): bool
    {
        return (bool) config('attribution.ingest_enabled', false);
    }

    public static function orderLinkEnabled(): bool
    {
        return (bool) config('attribution.order_link_enabled', false);
    }

    public static function eventsEnabled(): bool
    {
        return (bool) config('attribution.events_enabled', false);
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
        return (bool) config('attribution.canary_only', false);
    }

    public static function canaryMaxRows(): int
    {
        $n = (int) config('attribution.canary_max_rows', 20);

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
