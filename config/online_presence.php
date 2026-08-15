<?php

declare(strict_types=1);

return [
    // Включить/выключить трекинг
    'enabled' => env('ONLINE_PRESENCE_ENABLED', true),

    // Окно "онлайн"
    'window_seconds' => (int) env('ONLINE_WINDOW_SECONDS', 300),

    // Throttle на уровне middleware: как часто отправлять job на одного identity
    'identity_throttle_seconds' => (int) env('ONLINE_IDENTITY_THROTTLE_SECONDS', 20),

    // Micro throttle по IP (ранний выход, снижает нагрузку при спайках/ботах)
    'ip_micro_throttle_seconds' => (int) env('ONLINE_IP_MICRO_THROTTLE_SECONDS', 2),

    // Throttle внутри job (DB update): как часто реально обновлять last_seen
    'db_throttle_seconds' => (int) env('ONLINE_DB_THROTTLE_SECONDS', 20),

    // Cookie для гостей
    'guest_cookie' => env('ONLINE_GUEST_COOKIE', 'gid'),
    'guest_cookie_ttl_days' => (int) env('ONLINE_GUEST_COOKIE_TTL_DAYS', 365),

    // Писать ли IP в online_sessions (лучше false, если не нужно)
    'store_ip' => filter_var(env('ONLINE_STORE_IP', false), FILTER_VALIDATE_BOOL),

    // DDoS guard: лимит событий presence в минуту на IP
    'ip_limit_per_minute' => (int) env('ONLINE_IP_LIMIT_PER_MINUTE', 60),

    /**
     * Global guard:
     * Важно: глобальный ключ в Redis может стать hot-key под нагрузкой.
     * Поэтому проверка выполняется с семплированием (1 из N запросов).
     */
    'global_limit_per_minute' => (int) env('ONLINE_GLOBAL_LIMIT_PER_MINUTE', 50000),
    'global_sample_n' => (int) env('ONLINE_GLOBAL_SAMPLE_N', 20),
    'silent_minutes' => (int) env('ONLINE_SILENT_MINUTES', 2),

    // Очередь
    'queue' => env('ONLINE_PRESENCE_QUEUE', 'presence'),

    // Retention (очистка sessions)
    'retention_days_guests' => (int) env('ONLINE_RETENTION_DAYS_GUESTS', 3),
    'retention_days_users' => (int) env('ONLINE_RETENTION_DAYS_USERS', 14),
];
