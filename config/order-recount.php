<?php

declare(strict_types=1);

return [
    'queue' => [
        'recount' => env('ORDER_RECOUNT_QUEUE', 'order-recount'),
        'scan'    => env('ORDER_RECOUNT_SCAN_QUEUE', 'order-recount-scan'),
    ],

    'scan' => [
        'chunk'              => (int) env('ORDER_RECOUNT_SCAN_CHUNK', 500),
        'max_tasks_per_run'  => (int) env('ORDER_RECOUNT_MAX_TASKS_PER_RUN', 20000),
        'batch_size'         => (int) env('ORDER_RECOUNT_BATCH_SIZE', 200),
        'dir_chunk'          => (int) env('ORDER_RECOUNT_DIR_CHUNK', 200),
        'dir_task_chunk'     => (int) env('ORDER_RECOUNT_DIR_TASK_CHUNK', 200),
    ],

    'lock' => [
        'store'       => env('ORDER_RECOUNT_LOCK_STORE', 'redis'),
        'ttl_seconds' => (int) env('ORDER_RECOUNT_LOCK_TTL', 2),
        'prefix'      => env('ORDER_RECOUNT_LOCK_PREFIX', 'order-recount'),
    ],

    'dedupe' => [
        'store'       => env('ORDER_RECOUNT_DEDUPE_STORE', 'redis'),
        // общий TTL (cron / manual / batch)
        'ttl_seconds' => (int) env('ORDER_RECOUNT_DEDUPE_TTL', 2),

        // специальный TTL для status-change
        'status_change_ttl_seconds' => 15,
        'prefix'      => env('ORDER_RECOUNT_DEDUPE_PREFIX', 'order-recount:dedupe'),
    ],

    'fail' => [
        'lock_after_fail_streak' => (int) env('ORDER_RECOUNT_LOCK_AFTER_FAIL', 2),
        'lock_minutes'           => (int) env('ORDER_RECOUNT_LOCK_MINUTES', 0),
    ],
];
