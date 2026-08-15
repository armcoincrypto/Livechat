<?php

use App\Gateways\Crypto\CryptoCash\Messages\PurchaseRequest;
use App\Gateways\Crypto\CryptoCash\Messages\PurchaseResponse;
use App\Gateways\Crypto\CryptoCash\Messages\FetchPaymentRequest;
use App\Gateways\Crypto\CryptoCash\Messages\FetchPaymentResponse;
use App\Gateways\Crypto\CryptoCash\Messages\PayoutRequest;
use App\Gateways\Crypto\CryptoCash\Messages\PayoutResponse;
use App\Gateways\Crypto\CryptoCash\Messages\FetchPayoutRequest;
use App\Gateways\Crypto\CryptoCash\Messages\FetchPayoutResponse;

return [

    'schema' => 1,

    'meta' => [
        'name'        => 'Crypto Cash (Crypto)',
        'alias'       => 'cryptocashcrypto',
        'version'     => '4.0',
        'description' => 'Крипто-шлюз Crypto Cash (импорт из старого JSON).',
        'category'    => 'crypto',

        'sorting'     => 100,
        'recommended' => false,
    ],

    'capabilities' => [
        'incoming' => true,
        'outgoing' => true,

        'features' => [
            'is_crypto'        => true,
            'auto_operations'  => true,

            // check_payment=true
            'polling' => [
                'incoming' => true,
                'outgoing' => true,
            ],
        ]
    ],

    'operations' => [
        'purchase' => [
            'type'           => 'incoming',
            'label'          => 'Создание платежа',
            'description'    => 'Создание входящего крипто-платежа.',
            'request_class'  => PurchaseRequest::class,
            'response_class' => PurchaseResponse::class,
        ],

        'fetch_payment' => [
            'type'           => 'incoming',
            'label'          => 'Проверка платежа',
            'description'    => 'Проверка входящего платежа через API.',
            'request_class'  => FetchPaymentRequest::class,
            'response_class' => FetchPaymentResponse::class,
        ],

        'payout' => [
            'type'           => 'outgoing',
            'label'          => 'Выплата средств',
            'description'    => 'Создание исходящей транзакции.',
            'request_class'  => PayoutRequest::class,
            'response_class' => PayoutResponse::class,
        ],

        'fetch_payout' => [
            'type'           => 'outgoing',
            'label'          => 'Проверка выплаты',
            'description'    => 'Проверка исходящей транзакции через API.',
            'request_class'  => FetchPayoutRequest::class,
            'response_class' => FetchPayoutResponse::class,
        ],
    ],

    'inputs' => [

        'merchant' => [
            'fields' => [
                [
                    'type'        => 'input',
                    'label'       => 'Public Key',
                    'key'         => 'public_key',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'Private Key',
                    'key'         => 'private_key',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
            ],

            'options_fields' => [
                [
                    'type'        => 'input',
                    'label'       => 'Макс. время регистрации в блокчейне (в мин.)',
                    'key'         => 'max_time_register_in_network',
                    'default'     => 30,
                    'value_type'  => 'int',
                    'required'    => false,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'Макс. время получения 1-го подтверждения (в часах.)',
                    'key'         => 'max_time_confirm_in_network',
                    'default'     => 2,
                    'value_type'  => 'int',
                    'required'    => false,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'Мин. кол-во подтверждений',
                    'key'         => 'min_confirm',
                    'default'     => 0,
                    'value_type'  => 'int',
                    'required'    => false,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'Код валюты сети (network code)',
                    'key'         => 'network_code_currency',
                    'value_type'  => 'string',
                    'required'    => false,
                ],
            ],
        ],

        'pay' => [
            'fields' => [
                [
                    'type'        => 'input',
                    'label'       => 'Public Key',
                    'key'         => 'public_key',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'Private Key',
                    'key'         => 'private_key',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
            ],

            'options_fields' => [
                // нет опций в старом JSON
            ],
        ],
    ],
];
