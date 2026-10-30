<?php

declare(strict_types=1);

return [
    /**
     * Optional Redis/cache memo for SystemLogService state transitions.
     * Default true — matches historical env(SYSTEM_LOGS_CACHE_STATE, true).
     */
    'cache_state' => filter_var(env('SYSTEM_LOGS_CACHE_STATE', true), FILTER_VALIDATE_BOOLEAN),
];
