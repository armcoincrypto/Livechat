<?php

use App\Gateways\Fiat\Bydex\Messages\PayoutRequest;
use App\Gateways\Fiat\Bydex\Messages\PayoutResponse;
use App\Gateways\Fiat\Bydex\Messages\FetchPayoutRequest;
use App\Gateways\Fiat\Bydex\Messages\FetchPayoutResponse;

return [

    'schema' => 1,

    'meta' => [
        'name'        => 'Bydex',
        'alias'       => 'bydex',
        'version'     => '4.0',
        'description' => 'Шлюз Bydex для выплат (импорт из старого конфига).',
        'category'    => 'fiat',

        'sorting'     => 100,
        'recommended' => false,
    ],

    'capabilities' => [
        'incoming' => false,
        'outgoing' => true,

        'features' => [
            'is_crypto'         => false,
            'supports_balance'  => true,
            'auto_operations'   => true,

            // polling выплат если будет FetchPayoutRequest/Response
            'polling' => [
                'incoming' => false,
                'outgoing' => true,
            ],
        ],
    ],

    'operations' => [
        'payout' => [
            'type'           => 'outgoing',
            'label'          => 'Выплата средств',
            'description'    => 'Создаёт выплату и возвращает результат.',
            'request_class'  => PayoutRequest::class,
            'response_class' => PayoutResponse::class,
        ],

        // включай только если реально реализуешь fetch_payout
        'fetch_payout' => [
            'type'           => 'outgoing',
            'label'          => 'Проверка выплаты (polling)',
            'description'    => 'Получает статус выплаты через API.',
            'request_class'  => FetchPayoutRequest::class,
            'response_class' => FetchPayoutResponse::class,
        ],
    ],

    'inputs' => [
        'merchant' => [
            'fields' => [],
            'options_fields' => [],
        ],

        'pay' => [
            'fields' => [
                [
                    'type'        => 'input',
                    'label'       => 'API Login',
                    'key'         => 'api_login',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'API Key',
                    'key'         => 'api_key',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
            ],

            'options_fields' => [
                [
                    'type'        => 'select',
                    'label'       => 'Способ выплаты',
                    'key'         => 'method_pay',
                    'options'     => [
                        'Card' => 'Карты',
                        'SBP'  => 'SPB',
                    ],
                    'value_type'  => 'string',
                    'required'    => true,
                ],
            ],
        ],
    ],
];
