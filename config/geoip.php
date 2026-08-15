<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Locale
    |--------------------------------------------------------------------------
    */
    'locale' => env('GEOIP_LOCALE', 'auto'),
    'locale_fallback' => env('GEOIP_LOCALE_FALLBACK', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Error handling
    |--------------------------------------------------------------------------
    | throw | null_location | report
    */
    'fail_mode' => env('GEOIP_FAIL_MODE', 'null_location'),

    /*
    |--------------------------------------------------------------------------
    | IP source
    |--------------------------------------------------------------------------
    | request | remote_addr_only
    */
    'ip_source' => env('GEOIP_IP_SOURCE', 'request'),

    /*
    |--------------------------------------------------------------------------
    | Cache policy
    |--------------------------------------------------------------------------
    | public_only | all | none
    */
    'cache_policy' => env('GEOIP_CACHE_POLICY', 'public_only'),

    'skip_private_ips'  => true,
    'skip_reserved_ips' => true,

    /*
    |--------------------------------------------------------------------------
    | MaxMind databases
    |--------------------------------------------------------------------------
    */
    'databases' => [
        'city'    => storage_path('geoip/GeoLite2-City.mmdb'),
        'country' => storage_path('geoip/GeoLite2-Country.mmdb'),
        'asn'     => storage_path('geoip/GeoLite2-ASN.mmdb'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache (L2)
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled'      => true, // базовый fallback
        'store'        => null,
        'prefix'       => 'geoip:',
        'ttl_hit'      => 86400,
        'ttl_miss'     => 3600,
        'lock_seconds' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Local in-memory cache (L1)
    |--------------------------------------------------------------------------
    */
    'local_cache' => [
        'enabled'   => true, // базовый fallback
        'max_items' => 5000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit breaker
    |--------------------------------------------------------------------------
    */
    'circuit_breaker' => [
        'enabled'          => true,
        'cooldown_seconds' => 120,
        'threshold'        => 5,
        'window_seconds'   => 60,
        'prefix'           => 'geoip:cb:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Log limiter
    |--------------------------------------------------------------------------
    */
    'log_limiter' => [
        'enabled' => true,
        'prefix'  => 'geoip:log:',
        'ttl'     => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Runtime feature toggles (DEFAULTS)
    |--------------------------------------------------------------------------
    | Реальные значения читаются из DynamicConfig (только 4 ключа из UI).
    | Этот блок — fallback, если DynamicConfig недоступен/ключ не задан.
    */
    'toggles' => [
        // 1) Определять страну и город по IP
        'enabled'          => true,

        // 2) Кешировать результаты (быстрее)
        'cache_enabled'    => true,

        // 3) Улучшать результат (pipeline)
        'pipeline_enabled' => true,

        // 4) Аварийный режим: использовать только кеш
        'readonly_cache'   => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | DynamicConfig integration
    |--------------------------------------------------------------------------
    | Используем DynamicConfig ТОЛЬКО для 4 параметров из UI.
    | Остальные настройки (ttl, базы, local_cache, breaker, updater, pipeline-list)
    | остаются обычными значениями из config/geoip.php.
    */
    'dynamic_config' => [
        // Включить/выключить чтение из DynamicConfig
        'enabled' => true,

        // Итоговый ключ = prefix + key (например geoip.toggles.enabled)
        'prefix' => 'geoip.toggles.',

        // Только эти ключи читаем из DynamicConfig
        'keys' => [
            'enabled',
            'cache_enabled',
            'pipeline_enabled',
            'readonly_cache',
        ],

        // Кеш значений toggles (сек)
        'ttl' => 30,

        // Префикс кеш-ключей для сохранённых значений toggles
        'cache_prefix' => 'geoip:dyn:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug / Explain (config-only)
    |--------------------------------------------------------------------------
    | Не управляется через DynamicConfig (системная опция).
    */
    'debug' => [
        'enabled' => env('GEOIP_DEBUG_ENABLED', false),
        'include_processor_trace' => true,
        'include_timings' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | GeoIP Pipeline (post-processors)
    |--------------------------------------------------------------------------
    */
    'pipeline' => [
        \iEXPackages\GeoIp\Processors\NormalizeStringsProcessor::class,
        \iEXPackages\GeoIp\Processors\ExplainMetaProcessor::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | MaxMind updater
    |--------------------------------------------------------------------------
    */
    'update' => [
        'enabled'     => env('GEOIP_UPDATE_ENABLED', false),
        'work_dir'    => storage_path('geoip/_work'),
        'account_id'  => env('MAXMIND_ACCOUNT_ID'),
        'license_key' => env('MAXMIND_LICENSE_KEY'),
        'editions' => [
            'city'    => 'GeoLite2-City',
            'country' => 'GeoLite2-Country',
            'asn'     => 'GeoLite2-ASN',
        ],
    ],
];
