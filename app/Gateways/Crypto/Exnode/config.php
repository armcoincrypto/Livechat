<?php

declare(strict_types=1);

use App\Gateways\Crypto\Exnode\Messages\HealthRequest;
use App\Gateways\Crypto\Exnode\Messages\OptionsRequest;
use App\Gateways\Crypto\Exnode\Messages\OptionsResponse;
use App\Gateways\Crypto\Exnode\Messages\PurchaseRequest;
use App\Gateways\Crypto\Exnode\Messages\PurchaseResponse;
use App\Gateways\Crypto\Exnode\Messages\FetchPaymentRequest;
use App\Gateways\Crypto\Exnode\Messages\FetchPaymentResponse;

use App\Gateways\Crypto\Exnode\Messages\PayoutRequest;
use App\Gateways\Crypto\Exnode\Messages\PayoutResponse;
use App\Gateways\Crypto\Exnode\Messages\FetchPayoutRequest;
use App\Gateways\Crypto\Exnode\Messages\FetchPayoutResponse;
use iEXPackages\Payments\Core\Engine\HealthResponse;

return [

    'schema' => 1,

    'meta' => [
        'name'        => 'Exnode',
        'alias'       => 'exnode',
        'version'     => '4.0',
        'description' => 'Шлюз Exnode для приёма и выплат криптовалют.',
        'category'    => 'crypto',

        // UI
        'sorting'     => 0,
        'recommended' => false,
        'group'       => '',
    ],

    'capabilities' => [
        'incoming' => true,
        'outgoing' => true,

        'features' => [
            'is_crypto'         => true,
            'supports_balance'  => true,   // в старом balance=true
            'supports_polling'  => true,   // check_payment=true
            'supports_callback' => false,
            'auto_operations'   => true,
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
            'description'    => 'Создаёт входящий платёж/адрес в Exnode.',
            'request_class'  => PurchaseRequest::class,
            'response_class' => PurchaseResponse::class,
        ],

        'fetch_payment' => [
            'type'           => 'incoming',
            'label'          => 'Проверка оплаты (polling)',
            'description'    => 'Получает статус входящего платежа через API Exnode.',
            'request_class'  => FetchPaymentRequest::class,
            'response_class' => FetchPaymentResponse::class,
        ],

        'payout' => [
            'type'           => 'outgoing',
            'label'          => 'Выплата средств',
            'description'    => 'Создаёт выплату в Exnode.',
            'request_class'  => PayoutRequest::class,
            'response_class' => PayoutResponse::class,
        ],

        'fetch_payout' => [
            'type'           => 'outgoing',
            'label'          => 'Проверка выплаты (polling)',
            'description'    => 'Получает статус выплаты через API Exnode.',
            'request_class'  => FetchPayoutRequest::class,
            'response_class' => FetchPayoutResponse::class,
        ],

        // Если нужен Options Engine для options_api_method (getCurrencies) — добавь service operation:
         'options' => [
             'type'           => 'service',
             'label'          => 'Options API',
             'description'    => 'Списки значений для select-полей UI.',
             'request_class'  => OptionsRequest::class,
             'response_class' => OptionsResponse::class,
         ],

        'health' => [
            'type'           => 'service',
            'label'          => 'Проверка доступности шлюза',
            'description'    => 'Минимальный health-check без побочных эффектов.',
            'request_class'  => HealthRequest::class,
            'response_class' => HealthResponse::class,
        ],

    ],

    'inputs' => [

        'merchant' => [
            'fields' => [
                [
                    'type'       => 'input',
                    'label'      => 'Private Key',
                    'key'        => 'private_key',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
                [
                    'type'       => 'input',
                    'label'      => 'Public Key',
                    'key'        => 'public_key',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
            ],

            'options_fields' => [
                [
                    'type'       => 'input',
                    'label'      => 'Макс. время регистрации в блокчейне (в мин.)',
                    'key'        => 'max_time_register_in_network',
                    'default'    => 30,
                    'value_type' => 'int',
                    'required'   => false,
                ],
                [
                    'type'       => 'input',
                    'label'      => 'Макс. время получения 1-го подтверждения (в часах.)',
                    'key'        => 'max_time_confirm_in_network',
                    'default'    => 2,
                    'value_type' => 'int',
                    'required'   => false,
                ],
                [
                    'type'               => 'select',
                    'label'              => 'Код валюты (network code)',
                    'key'                => 'network_code_currency',
                    'options_api_method' => 'getCurrencies',
                    'value_type'         => 'string',
                    'required'           => false,
                ],
            ],
        ],

        'pay' => [
            'fields' => [
                [
                    'type'       => 'input',
                    'label'      => 'Private Key',
                    'key'        => 'private_key',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
                [
                    'type'       => 'input',
                    'label'      => 'Public Key',
                    'key'        => 'public_key',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
            ],

            'options_fields' => [
                // пусто, как в старом
            ],
        ],
    ],
];
