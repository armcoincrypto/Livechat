<?php

declare(strict_types=1);

namespace App\Services\Rates\FastMarket;

/**
 * Independent feature flags for the fast market / quote plane.
 *
 * Values come from config/fast_market.php (env read once at config build).
 * Do NOT call env() here — that silently defaults under config:cache.
 *
 * Distinct from BESTCHANGE_L1_*. All flags default false.
 */
final class FastMarketFlags
{
    public const PLANE = 'FAST_MARKET_PLANE_ENABLED';

    public const PUBLIC_QUOTE = 'FAST_PUBLIC_QUOTE_ENABLED';

    public const ORDER_QUOTE = 'FAST_ORDER_QUOTE_ENABLED';

    public const SHADOW = 'FAST_MARKET_SHADOW_ENABLED';

    public const LOCKED_QUOTE = 'FAST_LOCKED_QUOTE_ENABLED';

    public const ORDER_QUOTE_SHADOW = 'FAST_ORDER_QUOTE_SHADOW_ENABLED';

    public static function planeEnabled(): bool
    {
        return (bool) config('fast_market.plane_enabled', false);
    }

    public static function publicQuoteEnabled(): bool
    {
        return (bool) config('fast_market.public_quote_enabled', false);
    }

    public static function orderQuoteEnabled(): bool
    {
        return (bool) config('fast_market.order_quote_enabled', false);
    }

    /**
     * Issue opaque short-lived locked quote tokens on public fast quotes.
     * Independent of order authority — tokens may be issued while order still
     * uses the legacy financial path.
     */
    public static function lockedQuoteEnabled(): bool
    {
        return self::planeEnabled()
            && self::publicQuoteEnabled()
            && (bool) config('fast_market.locked_quote_enabled', false);
    }

    /**
     * Validate locked quote in parallel with legacy order pricing (no freeze).
     */
    public static function orderQuoteShadowEnabled(): bool
    {
        return self::planeEnabled()
            && (bool) config('fast_market.order_quote_shadow_enabled', false);
    }

    /**
     * Shadow compares fast candidate vs persisted quote without changing output.
     * Requires plane enabled; ignored when public quote is already authoritative.
     */
    public static function shadowEnabled(): bool
    {
        return self::planeEnabled() && (bool) config('fast_market.shadow_enabled', false);
    }

    public static function producerDaemonPreferred(): bool
    {
        return (bool) config('fast_market.producer_daemon', true);
    }

    public static function schedulerFallbackEnabled(): bool
    {
        return (bool) config('fast_market.scheduler_fallback', false);
    }

    /**
     * Laravel everyMinute refresh runs only as explicit fallback when the
     * systemd daemon is not the preferred cadence owner.
     */
    public static function schedulerRefreshAdmitted(): bool
    {
        if (! self::planeEnabled()) {
            return false;
        }
        if (self::producerDaemonPreferred() && ! self::schedulerFallbackEnabled()) {
            return false;
        }

        return true;
    }

    public static function freshnessThresholdSeconds(): int
    {
        return max(15, (int) config('fast_market.fresh_seconds', 60));
    }

    public static function graceThresholdSeconds(): int
    {
        $fresh = self::freshnessThresholdSeconds();

        return max($fresh, (int) config('fast_market.grace_seconds', 120));
    }

    public static function expiredThresholdSeconds(): int
    {
        $grace = self::graceThresholdSeconds();

        return max($grace, (int) config('fast_market.expired_seconds', 180));
    }

    /**
     * Max age of persisted course_value/rate_value allowed as fallback when
     * the fast snapshot is expired/missing. Continuity only — never "fresh".
     */
    public static function maxFallbackRateAgeSeconds(): int
    {
        return max(60, (int) config('fast_market.max_fallback_rate_age_seconds', 1200));
    }

    public static function snapshotRefreshSeconds(): int
    {
        return max(15, min(60, (int) config('fast_market.snapshot_refresh_seconds', 30)));
    }

    public static function quoteTtlSeconds(): int
    {
        return max(15, (int) config('fast_market.quote_ttl_seconds', 60));
    }

    public static function producerLockSeconds(): int
    {
        return max(20, (int) config('fast_market.producer_lock_seconds', 55));
    }

    public static function failureBackoffSeconds(): int
    {
        return max(5, min(120, (int) config('fast_market.failure_backoff_seconds', 15)));
    }

    public static function failureBackoffMaxSeconds(): int
    {
        $base = self::failureBackoffSeconds();

        return max($base, min(300, (int) config('fast_market.failure_backoff_max_seconds', 60)));
    }
}
