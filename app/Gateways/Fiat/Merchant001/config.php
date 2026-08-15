<?php

declare(strict_types=1);

use App\Gateways\Fiat\Merchant001\Messages\OptionsRequest;
use App\Gateways\Fiat\Merchant001\Messages\OptionsResponse;
use App\Gateways\Fiat\Merchant001\Messages\PurchaseRequest;
use App\Gateways\Fiat\Merchant001\Messages\PurchaseResponse;
use App\Gateways\Fiat\Merchant001\Messages\FetchPaymentRequest;
use App\Gateways\Fiat\Merchant001\Messages\FetchPaymentResponse;
use App\Gateways\Fiat\Merchant001\Messages\PayoutRequest;
use App\Gateways\Fiat\Merchant001\Messages\PayoutResponse;
use App\Gateways\Fiat\Merchant001\Messages\FetchPayoutRequest;
use App\Gateways\Fiat\Merchant001\Messages\FetchPayoutResponse;

return [

    /*
     * Версия схемы config.php.
     */
    'schema' => 1,

    /*
     * Базовая информация о шлюзе.
     */
    'meta' => [
        'name'        => 'Merchant001',
        'alias'       => 'merchant001',
        'version'     => '4.0',
        'description' => 'Merchant001 – приём и выплаты (checkout + реквизиты), без callback-успех/ошибка.',
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
            'supports_callback' => true,

            // авто-операции через __call() (purchase/fetchPayment/payout/fetchPayout)
            'auto_operations'   => true,
        ],

        // флаги по callback-типам (верхний уровень)
        'callbacks' => [
            'ipn'        => true,
            'return_url' => true, // is_callback_without_success = true
            'cancel_url' => true, // is_callback_without_failed  = true
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
            'description'    => 'Создаёт платёж и возвращает реквизиты или редирект (в зависимости от type_method_receiving_pay).',
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
            'description'    => 'Создаёт выплату через Merchant001.',
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

    /*
     * UI / Inputs:
     * Здесь описываются поля конфигурации шлюза.
     */
    'inputs' => [

        // --------------------------------------------------------------
        // MERCHANT (приём)
        // --------------------------------------------------------------
        'merchant' => [

            'fields' => [
                [
                    'type'       => 'input',
                    'label'      => 'API Token',
                    'key'        => 'api_token',
                    'is_hidden'  => true,
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
                        '0' => 'Переадресация на (платежную форму)',
                        '1' => 'Выдача реквизитов',
                    ],
                    'value_type' => 'int',
                    'required'   => false,
                ],
                [
                    'type'         => 'select',
                    'label'        => 'Способ оплаты',
                    'key'           => 'method_pay',
                    'options_api_method' => 'getCurrencies',
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
             * Новый чистый callback-блок.
             *
             * Маппинг старых ключей:
             * - callback_id_from_merchant      -> callback.order_id_field
             * - is_merchant_callback_urls      -> callback.enabled
             * - type_callbacks_route           -> callback.route_name
             * - is_callback_without_success    -> callback.success_url_enabled = false
             * - is_callback_without_failed     -> callback.fail_url_enabled    = false
             */
            'callback' => [
                'enabled'              => true,
                'order_id_field'       => 'transaction.id',      // было callback_id_from_merchant
                'route_name'           => 'merchant.webhook',    // было type_callbacks_route
                'ip_whitelist_enabled' => false,                 // в старом нет

                // управление генерацией return/success/fail URL в админке
                'send_return_urls'     => true,                  // было is_merchant_callback_urls
                'success_url_enabled'  => false,                 // было is_callback_without_success = true
                'fail_url_enabled'     => false,                 // было is_callback_without_failed  = true
            ],
        ],

        // --------------------------------------------------------------
        // PAY (выплаты)
        // --------------------------------------------------------------
        'pay' => [

            'fields' => [
                [
                    'type'       => 'input',
                    'label'      => 'API Token',
                    'key'        => 'api_token',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
            ],

            'options_fields' => [
                [
                    'type'         => 'select',
                    'label'        => 'Способ выплаты',
                    'key'          => 'method_pay',
                    'options_file' => 'merchant001_codes_withdrawal.json',
                    'value_type'   => 'string',
                    'required'     => false,
                ],
            ],
        ],
    ],
];
