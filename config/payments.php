<?php


return [

    /*
     * Ручная карта alias → класс шлюза.
     * Можно расширять, либо вообще оставить пустым и полагаться на автопоиск.
     */
    'gateways' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Ограничения доступных платёжных шлюзов (по alias)
    |--------------------------------------------------------------------------
    |
    | Источник: .env → PAYMENTS_ALLOWED_GATEWAY_ALIASES
    | Формат: alias1,alias2,alias3
    |
    | - пусто / null → разрешены ВСЕ шлюзы
    | - список       → разрешены ТОЛЬКО указанные
    |
    */

    'allowed_gateway_aliases' => array_values(
        array_filter(
            array_map(
                static fn ($v) => trim($v),
                explode(',', (string) env('PAYMENTS_ALLOWED_GATEWAY_ALIASES', ''))
            ),
            static fn ($v) => $v !== ''
        )
    ),


    /*
     * Пути для автопоиска шлюзов.
     * GatewayManager будет искать файлы Gateway.php и регистрировать их по getAlias().
     */
    'scan' => [
        [
            'namespace' => 'App\\Gateways\\Crypto',
            'path'      => base_path('app/Gateways/Crypto'),
        ],

        [
            'namespace' => 'App\\Gateways\\Fiat',
            'path'      => base_path('app/Gateways/Fiat'),
        ],
    ],

    'logging' => [
        'model' => \App\Models\PaymentGatewayLog::class,
    ],


    'callbacks' => [
        'payout_wait_minutes' => 60,
        'payout_max_attempts' => 60,

        // минимальная длина hash
        'hash_min_length' => 5,
    ],


    'options_cache_ttl' => 600, // 10 минут


    /*
  |--------------------------------------------------------------------------
  | Payments Sandbox & Replay configuration
  |--------------------------------------------------------------------------
  |
  | Sandbox — режим эмуляции работы платёжных шлюзов без реальных запросов.
  | Replay — механизм записи и воспроизведения HTTP-запросов/ответов.
  |
  | ❗ По умолчанию ВСЁ ВЫКЛЮЧЕНО (prod-safe).
  |
  */

    'sandbox' => [
        // Глобально: sandbox/replay выключены по умолчанию
        'enabled' => env('PAYMENTS_SANDBOX_ENABLED', false),

        // Разрешить запись replay (обычно только в sandbox)
        'record' => env('PAYMENTS_SANDBOX_RECORD', false),

        // Какие операции можно sandbox/replay (пусто = все)
        'operations' => [
            // 'purchase',
            'fetch_payment',
            'fetch_payout',
            // 'payout',
            // 'options',
        ],

        // Настройки replay (используются SandboxManager/ReplayStore)
        'replay' => [
            // true: брать последний подходящий replay (если будешь хранить несколько)
            'use_latest' => true,

            // TTL replay (сек): если запись старее — не используем
            'ttl_seconds' => (int) env('PAYMENTS_SANDBOX_REPLAY_TTL', 3600),

            // Если включишь — можно разрешить replay даже когда sandbox.enabled=false
            // (обычно не нужно)
            'allow_replay_without_sandbox' => false,
        ],
    ],
];
