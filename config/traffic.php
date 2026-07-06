<?php

declare(strict_types=1);

return [
    /*
     * TRAFFIC-P2a.1: scheduled site activity Telegram report (reuses support_chat telegram bot + chat id).
     */
    'site_report' => [
        'schedule_enabled' => filter_var(env('TRAFFIC_SITE_REPORT_SCHEDULE_ENABLED', '0'), FILTER_VALIDATE_BOOLEAN),
        'period_hours' => max(1, (int) env('TRAFFIC_SITE_REPORT_PERIOD_HOURS', 5)),
    ],

    /*
     * TRAFFIC-P2c: delta comparison and smart alert thresholds for Telegram reports.
     */
    'report' => [
        'alerts' => [
            'high_traffic_zero_orders_min_visitors' => max(1, (int) env('TRAFFIC_REPORT_ALERT_HIGH_TRAFFIC_ZERO_ORDERS_MIN_VISITORS', 100)),
            'visitor_drop_percent' => max(1, (int) env('TRAFFIC_REPORT_ALERT_VISITOR_DROP_PERCENT', 50)),
            'support_spike_min_delta' => max(1, (int) env('TRAFFIC_REPORT_ALERT_SUPPORT_SPIKE_MIN_DELTA', 5)),
            'api_drop_percent' => max(1, (int) env('TRAFFIC_REPORT_ALERT_API_DROP_PERCENT', 60)),
        ],

        /*
         * TRAFFIC-P2b: read-only nginx access log rollup for top pages/locales/countries.
         */
        'nginx' => [
            'enabled' => filter_var(env('TRAFFIC_REPORT_NGINX_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN),
            'access_log_path' => env(
                'TRAFFIC_REPORT_NGINX_ACCESS_LOG_PATH',
                ''
            ),
            'max_lines' => max(1000, (int) env('TRAFFIC_REPORT_NGINX_MAX_LINES', 250000)),
            'top_pages_limit' => max(1, (int) env('TRAFFIC_REPORT_NGINX_TOP_PAGES_LIMIT', 5)),
            'top_countries_limit' => max(1, (int) env('TRAFFIC_REPORT_NGINX_TOP_COUNTRIES_LIMIT', 5)),
            'top_locales_limit' => max(1, (int) env('TRAFFIC_REPORT_NGINX_TOP_LOCALES_LIMIT', 4)),
        ],

        /*
         * LC-A: warn when online presence pipeline is stale while API traffic continues.
         */
        'presence' => [
            'stale_minutes' => max(1, (int) env('TRAFFIC_REPORT_PRESENCE_STALE_MINUTES', 15)),
            'queue_backlog_threshold' => max(1, (int) env('TRAFFIC_REPORT_PRESENCE_QUEUE_BACKLOG_THRESHOLD', 1000)),
            'min_api_hits_for_stale_warning' => max(1, (int) env('TRAFFIC_REPORT_PRESENCE_MIN_API_HITS', 100)),
        ],
    ],
];
