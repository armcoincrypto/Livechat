<?php

use App\Gateways\Crypto\WestWallet\Messages\PurchaseRequest;
use App\Gateways\Crypto\WestWallet\Messages\PurchaseResponse;
use App\Gateways\Crypto\WestWallet\Messages\FetchPaymentRequest;
use App\Gateways\Crypto\WestWallet\Messages\FetchPaymentResponse;
use App\Gateways\Crypto\WestWallet\Messages\PayoutRequest;
use App\Gateways\Crypto\WestWallet\Messages\PayoutResponse;
use App\Gateways\Crypto\WestWallet\Messages\FetchPayoutRequest;
use App\Gateways\Crypto\WestWallet\Messages\FetchPayoutResponse;

return [

    /*
     |--------------------------------------------------------------------------
     | Schema
     |--------------------------------------------------------------------------
     */
    'schema' => 1,

    /*
     |--------------------------------------------------------------------------
     | Meta
     |--------------------------------------------------------------------------
     */
    'meta' => [
        'name'        => 'WestWallet',
        'alias'       => 'westwallet',
        'version'     => '4.0',
        'description' => 'Крипто-шлюз WestWallet (импорт из старого JSON).',
        'category'    => 'crypto',

        'sorting'     => 100,
        'recommended' => false,
    ],

    /*
     |--------------------------------------------------------------------------
     | Capabilities
     |--------------------------------------------------------------------------
     */
    'capabilities' => [
        'incoming' => true,
        'outgoing' => true,

        'features' => [
            'is_crypto'         => true,
            'supports_balance'  => true,
            'auto_operations'   => true,

            // check_payment = true
            'polling' => [
                'incoming' => true,
                'outgoing' => true,
            ],
        ],

        // ipn_status_url = true
        'callbacks' => [
            'ipn'        => false,
            'return_url' => false,
            'cancel_url' => false,
        ],
    ],

    /*
     |--------------------------------------------------------------------------
     | Operations
     |--------------------------------------------------------------------------
     */
    'operations' => [

        // ===== Incoming =====
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

        // ===== Outgoing =====
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
            'description'    => 'Проверка исходящей транзакции.',
            'request_class'  => FetchPayoutRequest::class,
            'response_class' => FetchPayoutResponse::class,
        ],
    ],

    /*
     |--------------------------------------------------------------------------
     | Inputs
     |--------------------------------------------------------------------------
     */
    'inputs' => [

        /*
         |--------------------------------------------------
         | Merchant (приём средств)
         |--------------------------------------------------
         */
        'merchant' => [
            'fields' => [
                [
                    'type'        => 'input',
                    'label'       => 'Публичный ключ',
                    'key'         => 'public_key',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'Приватный ключ',
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

        /*
         |--------------------------------------------------
         | Pay (выплаты)
         |--------------------------------------------------
         */
        'pay' => [
            'fields' => [
                [
                    'type'        => 'input',
                    'label'       => 'Публичный ключ',
                    'key'         => 'public_key',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'Приватный ключ',
                    'key'         => 'private_key',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
            ],

            'options_fields' => [
                [
                    'type'        => 'select',
                    'label'       => 'Комиссия за транзакцию',
                    'key'         => 'priority_fee',
                    'options'     => [
                        'low'    => 'low',
                        'medium' => 'medium',
                        'high'   => 'high',
                    ],
                    'default'     => 'medium',
                    'value_type'  => 'string',
                    'required'    => false,
                ],
            ],
        ],
    ],
];
