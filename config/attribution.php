<?php

declare(strict_types=1);

/**
 * Batch 11 attribution dark-launch flags.
 * Values are baked at config build / config:cache time from .env.
 * Runtime readers must use config('attribution.*') — never env() after cache.
 */
return [
    'ingest_enabled' => filter_var(env('EXS_ATTRIBUTION_INGEST_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'order_link_enabled' => filter_var(env('EXS_ATTRIBUTION_ORDER_LINK_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'events_enabled' => filter_var(env('EXS_ATTRIBUTION_EVENTS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'canary_only' => filter_var(env('EXS_ATTRIBUTION_CANARY_ONLY', false), FILTER_VALIDATE_BOOLEAN),
    'canary_max_rows' => max(1, min(20, (int) env('EXS_ATTRIBUTION_CANARY_MAX_ROWS', 20))),
];
