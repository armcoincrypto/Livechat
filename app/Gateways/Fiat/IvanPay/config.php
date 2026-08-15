<?php

declare(strict_types=1);

use App\Gateways\Fiat\IvanPay\Messages\HealthRequest;
use App\Gateways\Fiat\IvanPay\Messages\PurchaseRequest;
use App\Gateways\Fiat\IvanPay\Messages\PurchaseResponse;
use App\Gateways\Fiat\IvanPay\Messages\FetchPaymentRequest;
use App\Gateways\Fiat\IvanPay\Messages\FetchPaymentResponse;
use App\Gateways\Fiat\IvanPay\Messages\PayoutRequest;
use App\Gateways\Fiat\IvanPay\Messages\PayoutResponse;
use App\Gateways\Fiat\IvanPay\Messages\FetchPayoutRequest;
use App\Gateways\Fiat\IvanPay\Messages\FetchPayoutResponse;
use iEXPackages\Payments\Core\Engine\HealthResponse;

return [

    /*
     * Версия схемы config.php.
     */
    'schema' => 1,

    /*
     * Базовая информация о шлюзе.
     */
    'meta' => [
        'name'        => 'IvanPay',
        'alias'       => 'ivanpay',
        'version'     => '4.0',
        'description' => 'IvanPay – приём и выплаты (карты/СБП).',
        'category'    => 'fiat',

        // UI
        'sorting'     => 0,
        'recommended' => false,
    ],

    /*
     * Возможности шлюза.
     */
    'capabilities' => [
        'incoming' => true,
        'outgoing' => true,

        'features' => [
            'is_crypto'         => false,
            'supports_checkout' => true,  // payment_options.is_checkout
            'supports_polling'  => true,  // payment_options.check_payment
            'supports_balance'  => false,
            'supports_callback' => false,

            // авто-операции через __call() (purchase/fetchPayment/payout/fetchPayout)
            'auto_operations'   => true,
        ],

        // callback’и не описаны в старом json → оставляем false
        'callbacks' => [
            'ipn'        => false,
            'return_url' => false,
            'cancel_url' => false,
        ],
    ],

    /*
     * Операции шлюза.
     */
    'operations' => [

        // ---------------- incoming ----------------

        'purchase' => [
            'type'           => 'incoming',
            'label'          => 'Создание платежа',
            'description'    => 'Создаёт платёж и возвращает реквизиты или редирект (если появится).',
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

        // ---------------- outgoing ----------------

        'payout' => [
            'type'           => 'outgoing',
            'label'          => 'Выплата средств',
            'description'    => 'Создаёт выплату через IvanPay.',
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

        'health' => [
            'type'           => 'service',
            'label'          => 'Проверка доступности шлюза',
            'description'    => 'Минимальный health-check без побочных эффектов.',
            'request_class'  => HealthRequest::class,
            'response_class' => HealthResponse::class,
        ],

    ],

    /*
     * UI / Inputs:
     * Здесь описываются ПОЛЯ КОНФИГУРАЦИИ ШЛЮЗА, которые заполняет админ.
     *
     * - inputs.merchant → конфиг приёма (incoming)
     * - inputs.pay      → конфиг выплат (outgoing)
     */
    'inputs' => [

        // --------------------------------------------------------------
        // MERCHANT (приём)
        // --------------------------------------------------------------
        'merchant' => [

            'fields' => [
                [
                    'type'       => 'input',
                    'label'      => 'Домен',
                    'key'        => 'api_domain',
                    'value_type' => 'string',
                    'required'   => true,
                ],
                [
                    'type'       => 'input',
                    'label'      => 'API Key',
                    'key'        => 'api_key',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
            ],

            'options_fields' => [
                [
                    'type'       => 'select',
                    'label'      => 'Способ приема оплаты',
                    'key'        => 'type_pay',
                    'options'    => [
                        'card' => 'Карты',
                        'spb'  => 'SPB',
                    ],
                    'value_type' => 'string',
                    'required'   => false,
                ],
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
                    'type'         => 'select',
                    'label'        => 'Название банка',
                    'key'          => 'bank_name',
                    'options_file' => 'ivanpay.json',
                    'value_type'   => 'string',
                    'required'     => false,
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
            ],

            /*
             * Новый чистый callback-блок (старый json его не содержит).
             * Оставляем выключенным — включишь, когда появится реальный callback.
             */
            'callback' => [
                'enabled'              => false,
                'order_id_field'       => null,
                'route_name'           => null,
                'ip_whitelist_enabled' => false,

                // UI-опции генерации return/success/fail URL (если захочешь)
                'send_return_urls'     => false,
                'success_url_enabled'  => true,
                'fail_url_enabled'     => true,
            ],
        ],

        // --------------------------------------------------------------
        // PAY (выплаты)
        // --------------------------------------------------------------
        'pay' => [

            'fields' => [
                [
                    'type'       => 'input',
                    'label'      => 'Домен',
                    'key'        => 'api_domain',
                    'value_type' => 'string',
                    'required'   => true,
                ],
                [
                    'type'       => 'input',
                    'label'      => 'API Key',
                    'key'        => 'api_key',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
            ],

            'options_fields' => [
                [
                    'type'       => 'select',
                    'label'      => 'Способ выплаты',
                    'key'        => 'type_pay',
                    'options'    => [
                        'card' => 'Карты',
                        'spb'  => 'SPB',
                    ],
                    'value_type' => 'string',
                    'required'   => false,
                ],
                [
                    'type'         => 'select',
                    'label'        => 'Название банка',
                    'key'          => 'bank_name',
                    'options_file' => 'ivanpay.json',
                    'value_type'   => 'string',
                    'required'     => false,
                ],
            ],
        ],
    ],
];
