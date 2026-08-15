<?php

declare(strict_types=1);

use App\Gateways\Fiat\Firekassa\Messages\OptionsRequest;
use App\Gateways\Fiat\Firekassa\Messages\OptionsResponse;
use App\Gateways\Fiat\Firekassa\Messages\PurchaseRequest;
use App\Gateways\Fiat\Firekassa\Messages\PurchaseResponse;
use App\Gateways\Fiat\Firekassa\Messages\FetchPaymentRequest;
use App\Gateways\Fiat\Firekassa\Messages\FetchPaymentResponse;

use App\Gateways\Fiat\Firekassa\Messages\PayoutRequest;
use App\Gateways\Fiat\Firekassa\Messages\PayoutResponse;
use App\Gateways\Fiat\Firekassa\Messages\FetchPayoutRequest;
use App\Gateways\Fiat\Firekassa\Messages\FetchPayoutResponse;

return [

    'schema' => 1,

    'meta' => [
        'name'        => 'Firekassa',
        'alias'       => 'firekassa',
        'version'     => '4.0',
        'description' => 'Firekassa – приём и выплаты (реквизиты), checkout включён, polling включён.',
        'category'    => 'fiat',

        // UI
        'sorting'     => 0,
        'recommended' => false,
    ],

    'capabilities' => [
        'incoming' => true,
        'outgoing' => true,

        'features' => [
            'is_crypto'         => false,
            'supports_checkout' => true,  // payment_options.is_checkout
            'supports_polling'  => true,  // payment_options.check_payment
            'supports_balance'  => false,
            'supports_callback' => false, // inputs.merchant.is_merchant_callback_urls = false
            'auto_operations'   => true,
        ],

        'callbacks' => [
            'ipn'        => false,
            'return_url' => false,
            'cancel_url' => false,
        ],
    ],

    // Оставляем для совместимости (если в проекте где-то ещё читается payment_options.*)
    'payment_options' => [
        'is_checkout'   => true,
        'check_payment' => true,
    ],

    'operations' => [

        // incoming
        'purchase' => [
            'type'           => 'incoming',
            'label'          => 'Создание платежа',
            'description'    => 'Создаёт входящий платёж и возвращает реквизиты.',
            'request_class'  => PurchaseRequest::class,
            'response_class' => PurchaseResponse::class,
        ],

        'fetch_payment' => [
            'type'           => 'incoming',
            'label'          => 'Проверка оплаты',
            'description'    => 'Получает статус входящего платежа через API.',
            'request_class'  => FetchPaymentRequest::class,
            'response_class' => FetchPaymentResponse::class,
        ],

        // outgoing
        'payout' => [
            'type'           => 'outgoing',
            'label'          => 'Выплата средств',
            'description'    => 'Создаёт выплату и возвращает результат.',
            'request_class'  => PayoutRequest::class,
            'response_class' => PayoutResponse::class,
        ],

        'fetch_payout' => [
            'type'           => 'outgoing',
            'label'          => 'Проверка выплаты',
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

        // -----------------------------------------------------------------
        // MERCHANT (incoming)
        // -----------------------------------------------------------------
        'merchant' => [

            'fields' => [
                [
                    'type'       => 'input',
                    'label'      => 'API Bearer Token',
                    'key'        => 'secret_key',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
                [
                    'type'       => 'input',
                    'label'      => 'API Sign Token',
                    'key'        => 'sign_token',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
                [
                    'type'       => 'input',
                    'label'      => 'Название сайта',
                    'key'        => 'site_name',
                    'value_type' => 'string',
                    'required'   => false,
                ],
                [
                    'type'       => 'select',
                    'label'      => 'Адрес сайта',
                    'key'        => 'site_url',
                    'options'    => [
                        'admin.gampay.cc'      => 'admin.gampay.cc (Для UAH)',
                        'admin.vanilapay.com'  => 'admin.vanilapay.com (Для RUB)',
                        'web.gampay.cc'        => 'web.gampay.cc (Для UAH, альтернатива)',
                        'web.vanilapay.com'    => 'web.vanilapay.com (Для RUB, альтернатива)',
                    ],
                    'value_type' => 'string',
                    'required'   => true,
                ],
            ],

            'options_fields' => [
                [
                    'type'       => 'select',
                    'label'      => 'Тип оплаты',
                    'key'        => 'type_method_receiving_pay',
                    'options'    => [
                        '1' => 'Выдача реквизитов',
                    ],
                    'default'    => 1,
                    'value_type' => 'int',
                    'required'   => false,
                ],
                [
                    'type'       => 'input',
                    'label'      => 'Мин. время ожидании поступлений (в мин.)',
                    'key'        => 'max_time_register_in_network',
                    'default'    => 30,
                    'value_type' => 'int',
                    'required'   => false,
                ],
                [
                    'type'       => 'input',
                    'label'      => 'Макс. время ожидании поступлений (в часах.)',
                    'key'        => 'max_time_confirm_in_network',
                    'default'    => 2,
                    'value_type' => 'int',
                    'required'   => false,
                ],
                [
                    'type'       => 'select',
                    'label'      => 'Способ оплаты',
                    'key'        => 'method_pay',
                    'options'    => [
                        'card' => 'Card',
                    ],
                    'default'    => 'card',
                    'value_type' => 'string',
                    'required'   => false,
                ],

                [
                    'type'               => 'select',
                    'label'              => 'Метод оплаты',
                    'key'                => 'site_account',
                    'options_api_method' => 'getCardMethods',
                    'value_type'         => 'string',
                    'required'           => false,
                ],
            ],

            // callback выключен (как было is_merchant_callback_urls = false)
            'callback' => [
                'enabled'              => false,
                'order_id_field'       => '',
                'route_name'           => '',
                'ip_whitelist_enabled' => false,

                'send_return_urls'     => false,
                'success_url_enabled'  => false,
                'fail_url_enabled'     => false,
            ],
        ],

        // -----------------------------------------------------------------
        // PAY (outgoing)
        // -----------------------------------------------------------------
        'pay' => [

            'fields' => [
                [
                    'type'       => 'input',
                    'label'      => 'API Bearer Token',
                    'key'        => 'secret_key',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
                [
                    'type'       => 'input',
                    'label'      => 'API Sign Token',
                    'key'        => 'sign_token',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
                [
                    'type'       => 'input',
                    'label'      => 'Название сайта',
                    'key'        => 'site_name',
                    'value_type' => 'string',
                    'required'   => false,
                ],
                [
                    'type'       => 'select',
                    'label'      => 'Адрес сайта',
                    'key'        => 'site_url',
                    'options'    => [
                        'admin.gampay.cc'      => 'admin.gampay.cc (Для UAH)',
                        'admin.vanilapay.com'  => 'admin.vanilapay.com (Для RUB)',
                        'web.gampay.cc'        => 'web.gampay.cc (Для UAH, альтернатива)',
                        'web.vanilapay.com'    => 'web.vanilapay.com (Для RUB, альтернатива)',
                    ],
                    'value_type' => 'string',
                    'required'   => true,
                ],
            ],

            'options_fields' => [
                [
                    'type'       => 'select',
                    'label'      => 'Способ выплаты',
                    'key'        => 'method_pay',
                    'options'    => [
                        'card' => 'Card',
                    ],
                    'default'    => 'card',
                    'value_type' => 'string',
                    'required'   => false,
                ],

                [
                    'type'               => 'select',
                    'label'              => 'Метод выплаты',
                    'key'                => 'site_account',
                    'options_api_method' => 'getCardMethods',
                    'value_type'         => 'string',
                    'required'           => false,
                ]
            ],
        ],
    ],
];
