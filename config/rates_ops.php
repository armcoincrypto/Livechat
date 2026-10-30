<?php

declare(strict_types=1);

/**
 * Ops/one-off rate tooling flags (baked for config:cache safety).
 */
return [
    'allow_legacy_rub_profit_bootstrap' => (string) env('EXSWAPING_ALLOW_LEGACY_RUB_PROFIT_BOOTSTRAP', ''),
];
