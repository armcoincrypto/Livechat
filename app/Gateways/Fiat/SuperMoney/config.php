<?php

use App\Gateways\Fiat\SuperMoney\Messages\PurchaseRequest;
use App\Gateways\Fiat\SuperMoney\Messages\PurchaseResponse;
use App\Gateways\Fiat\SuperMoney\Messages\FetchPaymentRequest;
use App\Gateways\Fiat\SuperMoney\Messages\FetchPaymentResponse;

use App\Gateways\Fiat\SuperMoney\Messages\PayoutRequest;
use App\Gateways\Fiat\SuperMoney\Messages\PayoutResponse;
use App\Gateways\Fiat\SuperMoney\Messages\FetchPayoutRequest;
use App\Gateways\Fiat\SuperMoney\Messages\FetchPayoutResponse;

return [

    'schema' => 1,

    'meta' => [
        'name'        => 'SuperMoney',
        'alias'       => 'supermoney',
        'version'     => '4.0',
        'description' => 'Шлюз SuperMoney (импорт из старого JSON).',
        'category'    => 'fiat',

        'sorting'     => 100,
        'recommended' => false,
    ],

    'capabilities' => [
        'incoming' => true,
        'outgoing' => true,

        'features' => [
            'support_fiat'        => true,
            'support_crypto'    => false,
            'supports_balance' => false,
            'auto_operations'  => true,

            // check_payment=true
            'polling' => [
                'incoming' => true,
                'outgoing' => true,
            ],
        ],

        'callbacks' => [
            'ipn'        => false,
            'return_url' => false,
            'cancel_url' => false,
        ],
    ],

    'operations' => [
        'purchase' => [
            'type'           => 'incoming',
            'label'          => 'Создание платежа',
            'description'    => 'Создаёт платёж и возвращает реквизиты.',
            'request_class'  => PurchaseRequest::class,
            'response_class' => PurchaseResponse::class,
        ],

        'fetch_payment' => [
            'type'           => 'incoming',
            'label'          => 'Проверка оплаты (polling)',
            'description'    => 'Проверка статуса входящего платежа через API.',
            'request_class'  => FetchPaymentRequest::class,
            'response_class' => FetchPaymentResponse::class,
        ],

        'payout' => [
            'type'           => 'outgoing',
            'label'          => 'Выплата средств',
            'description'    => 'Создание выплаты.',
            'request_class'  => PayoutRequest::class,
            'response_class' => PayoutResponse::class,
        ],

        'fetch_payout' => [
            'type'           => 'outgoing',
            'label'          => 'Проверка выплаты (polling)',
            'description'    => 'Проверка статуса выплаты через API.',
            'request_class'  => FetchPayoutRequest::class,
            'response_class' => FetchPayoutResponse::class,
        ],
    ],

    'inputs' => [

        'merchant' => [
            'fields' => [
                [
                    'type'        => 'input',
                    'label'       => 'API Domain',
                    'key'         => 'api_domain',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'API Token',
                    'key'         => 'api_auth_token',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'API Sign Token',
                    'key'         => 'api_sign_token',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
            ],

            'options_fields' => [
                [
                    'type'        => 'select',
                    'label'       => 'Способ приема оплаты',
                    'key'         => 'type_pay',
                    'options'     => [
                        'card' => 'Карты',
                        'sbp'  => 'SPB',
                    ],
                    'value_type'  => 'string',
                    'required'    => false,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'Мин. время ожидании поступлений (в мин.)',
                    'key'         => 'max_time_register_in_network',
                    'default'     => 30,
                    'value_type'  => 'int',
                    'required'    => false,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'Макс. время ожидании поступлений (в часах.)',
                    'key'         => 'max_time_confirm_in_network',
                    'default'     => 2,
                    'value_type'  => 'int',
                    'required'    => false,
                ],
            ],
        ],

        'pay' => [
            'fields' => [
                [
                    'type'        => 'input',
                    'label'       => 'API Domain',
                    'key'         => 'api_domain',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'API Token',
                    'key'         => 'api_auth_token',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'API Sign Token',
                    'key'         => 'api_sign_token',
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
                        'card' => 'Карты',
                        'sbp'  => 'SPB',
                    ],
                    'value_type'  => 'string',
                    'required'    => false,
                ],
            ],
        ],
    ],
];
