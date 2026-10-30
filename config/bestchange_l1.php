<?php

declare(strict_types=1);

/**
 * BestChange L1 generation-book authority / shadow / diagnostic flags.
 * Baked from .env at config build. Callers may still honor process getenv overrides.
 */
return [
    'authority_enabled' => filter_var(env('BESTCHANGE_L1_AUTHORITY_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'shadow_enabled' => filter_var(env('BESTCHANGE_L1_SHADOW_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'skip_identical_de_upsert' => filter_var(env('BESTCHANGE_L1_SKIP_IDENTICAL_DE_UPSERT', false), FILTER_VALIDATE_BOOLEAN),
    'same_snapshot_diagnostic' => filter_var(env('BESTCHANGE_L1_SAME_SNAPSHOT_DIAGNOSTIC', false), FILTER_VALIDATE_BOOLEAN),
];
