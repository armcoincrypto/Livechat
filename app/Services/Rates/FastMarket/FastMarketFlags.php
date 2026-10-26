<?php

declare(strict_types=1);

namespace App\Services\Rates\FastMarket;

/**
 * Independent feature flags for the fast market / quote plane.
 *
 * Distinct from BESTCHANGE_L1_* flags. All default false so production
 * behavior is unchanged until an authorized shadow/canary cutover.
 */
final class FastMarketFlags
{
    public const PLANE = 'FAST_MARKET_PLANE_ENABLED';

    public const PUBLIC_QUOTE = 'FAST_PUBLIC_QUOTE_ENABLED';

    public const ORDER_QUOTE = 'FAST_ORDER_QUOTE_ENABLED';

    public const SHADOW = 'FAST_MARKET_SHADOW_ENABLED';

    public static function planeEnabled(): bool
    {
        return self::envBool(self::PLANE, false);
    }

    public static function publicQuoteEnabled(): bool
    {
        return self::envBool(self::PUBLIC_QUOTE, false);
    }

    public static function orderQuoteEnabled(): bool
    {
        return self::envBool(self::ORDER_QUOTE, false);
    }

    /**
     * Shadow compares fast candidate vs persisted quote without changing output.
     * Requires plane enabled; ignored when public quote is already authoritative.
     */
    public static function shadowEnabled(): bool
    {
        return self::planeEnabled() && self::envBool(self::SHADOW, false);
    }

    public static function freshnessThresholdSeconds(): int
    {
        return max(15, self::envInt('FAST_MARKET_FRESH_SECONDS', 60));
    }

    public static function graceThresholdSeconds(): int
    {
        $fresh = self::freshnessThresholdSeconds();

        return max($fresh, self::envInt('FAST_MARKET_GRACE_SECONDS', 120));
    }

    public static function expiredThresholdSeconds(): int
    {
        $grace = self::graceThresholdSeconds();

        return max($grace, self::envInt('FAST_MARKET_EXPIRED_SECONDS', 180));
    }

    /**
     * Max age of persisted course_value/rate_value allowed as fallback when
     * the fast snapshot is expired/missing. Aligns with financial BC heartbeat
     * warning band (1200s) — not the 30–60s product target.
     */
    public static function maxFallbackRateAgeSeconds(): int
    {
        return max(60, self::envInt('FAST_MARKET_MAX_FALLBACK_RATE_AGE_SECONDS', 1200));
    }

    public static function snapshotRefreshSeconds(): int
    {
        return max(15, min(60, self::envInt('FAST_MARKET_SNAPSHOT_REFRESH_SECONDS', 30)));
    }

    public static function quoteTtlSeconds(): int
    {
        return max(15, self::envInt('FAST_MARKET_QUOTE_TTL_SECONDS', 60));
    }

    private static function envBool(string $key, bool $default): bool
    {
        $raw = function_exists('env') ? env($key, null) : null;
        if ($raw === null) {
            $g = getenv($key);
            $raw = ($g === false) ? null : $g;
        }
        if ($raw === null) {
            return $default;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    private static function envInt(string $key, int $default): int
    {
        $raw = function_exists('env') ? env($key, null) : null;
        if ($raw === null) {
            $g = getenv($key);
            $raw = ($g === false) ? null : $g;
        }
        if ($raw === null || $raw === '') {
            return $default;
        }

        return (int) $raw;
    }
}
