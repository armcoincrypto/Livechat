<?php

declare(strict_types=1);

/**
 * Partner monthly accrual — Wave B config.
 * Fail-closed defaults: feature and scheduler OFF.
 */
return [
    'enabled' => filter_var(env('PARTNER_ACCRUAL_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'scheduler_enabled' => filter_var(env('PARTNER_ACCRUAL_SCHEDULER_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    /** Business calendar for start/end/scheduled_for / due determination. */
    'business_timezone' => env('PARTNER_ACCRUAL_BUSINESS_TIMEZONE', env('APP_TIMEZONE', 'Europe/Moscow')),

    /** Max installments processed per runner invocation (catch-up bound). */
    'max_installments_per_run' => max(1, (int) env('PARTNER_ACCRUAL_BATCH_SIZE', 50)),

    /** Default variance around daily average (±percent). */
    'default_variance_percent' => max(0, min(90, (int) env('PARTNER_ACCRUAL_VARIANCE_PERCENT', 25))),

    /** USD display / generator minor unit scale (cents). */
    'minor_unit_scale' => 2,

    /** Fail closed: apply path requires both flags + explicit --apply. */
    'allow_apply' => filter_var(env('PARTNER_ACCRUAL_ALLOW_APPLY', false), FILTER_VALIDATE_BOOLEAN),
];
