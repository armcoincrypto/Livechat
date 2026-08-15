<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bestchange API
    |--------------------------------------------------------------------------
    |
    | Путь где будут храниться все разархивированные данные сервиса bestchange
    | хранилище временное после записи данных в базу она будет очищена
    |
    */
    'bestchange' => [
        'per_page' => 495,
        'api_url' => env('BESTCHANGE_API_URL', 'https://www.bestchange.app'),

        'api_urls' => [
            'https://bestchange.app',
            'https://mirror1.bestchange.app',
            'https://mirror2.bestchange.app',
            'https://mirror3.bestchange.app',
            'https://mirror4.bestchange.app',
        ],
        'allowed_hosts' => [
            'bestchange.app',
            'mirror1.bestchange.app',
            'mirror2.bestchange.app',
            'mirror3.bestchange.app',
            'mirror4.bestchange.app',
        ],

        'http' => [
            'connect_timeout' => 5,
            'retries' => 1,
            'retry_delay_ms' => 200,
            'rps' => 30,
            'rps_sleep_us' => 50_000,
        ],

        'engine' => [
            // Как сортировать список rates перед выбором:
            // api — доверяем порядку BestChange (лучше для “борьбы за позиции”)
            // asc/desc — сортировка по числу (rate/rankrate)
            'sort_order' => 'api', // api|asc|desc

            // Стабилизация диапазона position_num: "3-7"
            'position_window_seconds' => 600,
        ],

        'lock_ttl' => 900,

        'files' => [
            'codes' => storage_path('/app/bestchange/codes.json')
        ],
    ],

    'export_rates' => [
        'params' => [
            'atm' => 'atm',
            'card2card' => 'card2card',
            'cardverify' => 'cardverify',
            'delivery' => 'delivery',
            'juridical' => 'juridical',
            'manual' => 'manual',
            'otherin' => 'otherin',
            'otherout' => 'otherout',
            'reg' => 'reg',
            'verifying' => 'verifying'
        ]
    ]
];
