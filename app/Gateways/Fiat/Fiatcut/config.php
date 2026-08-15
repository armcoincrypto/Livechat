<?php

use App\Gateways\Fiat\Fiatcut\Messages\OptionsRequest;
use App\Gateways\Fiat\Fiatcut\Messages\OptionsResponse;
use App\Gateways\Fiat\FiatCut\Messages\PurchaseRequest;
use App\Gateways\Fiat\FiatCut\Messages\PurchaseResponse;
use App\Gateways\Fiat\FiatCut\Messages\FetchPaymentRequest;
use App\Gateways\Fiat\FiatCut\Messages\FetchPaymentResponse;

return [

    'schema' => 1,

    'meta' => [
        'name'        => 'FiatCut',
        'alias'       => 'fiatcut',
        'version'     => '4.0',
        'description' => 'Шлюз FiatCut (импорт из старого JSON).',
        'category'    => 'fiat',

        'sorting'     => 100,
        'recommended' => false,
    ],

    'capabilities' => [
        'incoming' => true,
        'outgoing' => false,

        'features' => [
            'is_crypto'         => false,
            'supports_checkout' => true,  // payment_options.is_checkout
            'auto_operations'   => true,

            // payment_options.check_payment
            'polling' => [
                'incoming' => true,
                'outgoing' => false,
            ]
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
            'description'    => 'Получает статус входящего платежа через API.',
            'request_class'  => FetchPaymentRequest::class,
            'response_class' => FetchPaymentResponse::class,
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
                    'label'       => 'API Merchant',
                    'key'         => 'api_merchant',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'API Token',
                    'key'         => 'api_token',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'API Адрес',
                    'key'         => 'api_url_address',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
            ],

            'options_fields' => [
                [
                    'type'        => 'select',
                    'label'       => 'Тип оплаты',
                    'key'         => 'type_method_receiving_pay',
                    'options'     => [
                        '1' => 'Выдача реквизитов',
                    ],
                    'value_type'  => 'string',
                    'required'    => false,
                ],
                [
                    'type'        => 'select',
                    'label'       => 'Способ приема оплаты',
                    'key'         => 'type_pay',
                    'options'     => [
                        'card'           => 'Карты',
                        'phone'          => 'Номер телефона',
                        'account_number' => 'Номер счета',
                    ],
                    'value_type'  => 'string',
                    'required'    => false,
                ],
                [
                    'type'               => 'select',
                    'label'              => 'Название банка',
                    'key'                => 'bank_name',
                    'options_api_method' => 'getCurrencies',
                    'value_type'         => 'string',
                    'required'           => false,
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

        // is_pay = 0
        'pay' => [
            'fields' => [],
            'options_fields' => [],
        ],
    ],
];
