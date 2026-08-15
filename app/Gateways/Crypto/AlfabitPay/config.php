<?php

use App\Gateways\Crypto\AlfabitPay\Messages\OptionsRequest;
use App\Gateways\Crypto\AlfabitPay\Messages\OptionsResponse;
use App\Gateways\Crypto\AlfabitPay\Messages\PurchaseRequest;
use App\Gateways\Crypto\AlfabitPay\Messages\PurchaseResponse;
use App\Gateways\Crypto\AlfabitPay\Messages\FetchPaymentRequest;
use App\Gateways\Crypto\AlfabitPay\Messages\FetchPaymentResponse;
use App\Gateways\Crypto\AlfabitPay\Messages\PayoutRequest;
use App\Gateways\Crypto\AlfabitPay\Messages\PayoutResponse;
use App\Gateways\Crypto\AlfabitPay\Messages\FetchPayoutRequest;
use App\Gateways\Crypto\AlfabitPay\Messages\FetchPayoutResponse;

return [

    'schema' => 1,

    'meta' => [
        'name'        => 'AlfaBit Pay',
        'alias'       => 'alfabitpay',
        'version'     => '4.0',
        'description' => 'Шлюз AlfaBit Pay (импорт из старого JSON).',
        'category'    => 'crypto',

        // старые поля:
        'group'       => 'Рекомендуемые',
        'sorting'     => 1,
        'recommended' => true,
    ],

    'capabilities' => [
        'incoming' => true,
        'outgoing' => true,

        'features' => [
            'is_crypto'         => true,
            'supports_polling'  => true,  // check_payment=true
            'supports_callback' => false, // ipn_status_url в JSON нет
            'auto_operations'   => true,
        ],
    ],

    'operations' => [
        'purchase' => [
            'type'           => 'incoming',
            'label'          => 'Создание платежа',
            'description'    => 'Создаёт платёж и возвращает результат.',
            'request_class'  => PurchaseRequest::class,
            'response_class' => PurchaseResponse::class,
        ],

        'fetch_payment' => [
            'type'           => 'incoming',
            'label'          => 'Проверка оплаты (polling)',
            'description'    => 'Получает статус входящего платежа через API.',
            'request_class'  => FetchPaymentRequest::class,
            'response_class' => FetchPaymentResponse::class,
        ],

        'payout' => [
            'type'           => 'outgoing',
            'label'          => 'Выплата средств',
            'description'    => 'Создаёт выплату и возвращает результат.',
            'request_class'  => PayoutRequest::class,
            'response_class' => PayoutResponse::class,
        ],

        'fetch_payout' => [
            'type'           => 'outgoing',
            'label'          => 'Проверка выплаты (polling)',
            'description'    => 'Получает статус выплаты через API.',
            'request_class'  => FetchPayoutRequest::class,
            'response_class' => FetchPayoutResponse::class,
        ],

        'options' => [
            'type' => 'service',
            'label' => 'Options API',
            'request_class' => OptionsRequest::class,
            'response_class' => OptionsResponse::class,
        ],
    ],

    'inputs' => [

        'merchant' => [
            'fields' => [
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
                    'type'               => 'select',
                    'label'              => 'Преобразовать к',
                    'key'                => 'convert_to',
                    'options_api_method' => 'getExchangeRate',
                    'value_type'         => 'string',
                    'required'           => false,
                ],
                [
                    'type'               => 'select',
                    'label'              => 'Код валюты',
                    'key'                => 'network_code_currency',
                    'options_api_method' => 'getCurrencies',
                    'value_type'         => 'string',
                    'required'           => false,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'Не подтверждать транзакцию, если процент риска AML выше чем',
                    'key'         => 'merchant_aml_percent',
                    'default'     => 0,
                    'value_type'  => 'float',
                    'required'    => false,
                ],
            ],
        ],

        'pay' => [
            'fields' => [
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
                // в старом JSON для pay options_fields нет
            ],
        ],
    ],
];
