<?php

declare(strict_types=1);

/**
 * Kobbopay gateway env-backed settings (fallback after merchant vault).
 */
return [
    'api_base' => (string) env('KOBBOPAY_API_BASE', ''),
    'webhook_secret' => (string) env('KOBBOPAY_WEBHOOK_SECRET', ''),
    'webhook_tolerance_seconds' => max(1, (int) env('KOBBOPAY_WEBHOOK_TOLERANCE_SECONDS', 300)),
];
