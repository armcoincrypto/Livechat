<?php

declare(strict_types=1);

/**
 * Fast market / quote plane configuration.
 *
 * All FAST_MARKET_* environment variables are read HERE so values survive
 * `php artisan config:cache`. Application code must use config('fast_market.*')
 * via FastMarketFlags — never env() at runtime.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Feature flags (all default false — production behavior unchanged)
    |--------------------------------------------------------------------------
    */
    'plane_enabled' => (bool) filter_var(env('FAST_MARKET_PLANE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'shadow_enabled' => (bool) filter_var(env('FAST_MARKET_SHADOW_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'public_quote_enabled' => (bool) filter_var(env('FAST_PUBLIC_QUOTE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'order_quote_enabled' => (bool) filter_var(env('FAST_ORDER_QUOTE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    // Issue opaque quote_token on public fast quotes (order authority stays separate).
    'locked_quote_enabled' => (bool) filter_var(env('FAST_LOCKED_QUOTE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    // Parallel validate locked quote vs legacy order pricing (no financial freeze).
    'order_quote_shadow_enabled' => (bool) filter_var(env('FAST_ORDER_QUOTE_SHADOW_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Producer
    |--------------------------------------------------------------------------
    | Preferred cadence owner is the systemd daemon loop (~30s). Laravel
    | everyMinute refresh is opt-in fallback only (do not dual-fire by default).
    */
    'producer_daemon' => (bool) filter_var(env('FAST_MARKET_PRODUCER_DAEMON', true), FILTER_VALIDATE_BOOLEAN),
    'scheduler_fallback' => (bool) filter_var(env('FAST_MARKET_SCHEDULER_FALLBACK', false), FILTER_VALIDATE_BOOLEAN),
    'snapshot_refresh_seconds' => max(15, min(60, (int) env('FAST_MARKET_SNAPSHOT_REFRESH_SECONDS', 30))),
    'producer_lock_seconds' => max(20, (int) env('FAST_MARKET_PRODUCER_LOCK_SECONDS', 55)),
    'failure_backoff_seconds' => max(5, min(120, (int) env('FAST_MARKET_FAILURE_BACKOFF_SECONDS', 15))),
    'failure_backoff_max_seconds' => max(15, min(300, (int) env('FAST_MARKET_FAILURE_BACKOFF_MAX_SECONDS', 60))),

    /*
    |--------------------------------------------------------------------------
    | Freshness / fallback
    |--------------------------------------------------------------------------
    */
    'fresh_seconds' => max(15, (int) env('FAST_MARKET_FRESH_SECONDS', 60)),
    'grace_seconds' => max(15, (int) env('FAST_MARKET_GRACE_SECONDS', 120)),
    'expired_seconds' => max(15, (int) env('FAST_MARKET_EXPIRED_SECONDS', 180)),
    'max_fallback_rate_age_seconds' => max(60, (int) env('FAST_MARKET_MAX_FALLBACK_RATE_AGE_SECONDS', 1200)),
    // Short-lived lock: 30s matches ~snapshot cadence; frontend should refresh ~15–20s.
    'quote_ttl_seconds' => max(15, (int) env('FAST_MARKET_QUOTE_TTL_SECONDS', 30)),
];
