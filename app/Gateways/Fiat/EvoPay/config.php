<?php

declare(strict_types=1);

use App\Gateways\Fiat\EvoPay\Messages\PurchaseRequest;
use App\Gateways\Fiat\EvoPay\Messages\PurchaseResponse;
use App\Gateways\Fiat\EvoPay\Messages\FetchPaymentRequest;
use App\Gateways\Fiat\EvoPay\Messages\FetchPaymentResponse;

return [

    /*
     * Версия схемы конфигурации шлюза.
     */
    'schema' => 1,

    /*
     * META-информация о шлюзе.
     */
    'meta' => [
        'name'        => 'EvoPay',
        'alias'       => 'evopay',
        'version'     => '4.0',
        'description' => 'EvoPay: приём платежей (merchant).',
        'category'    => 'fiat',

        // UI
        'sorting'     => 100,
        'recommended' => false,
    ],

    /*
     * Возможности шлюза.
     *
     * incoming = merchant (приём)
     * outgoing = pay (выплаты)
     */
    'capabilities' => [
        'incoming' => true,
        'outgoing' => false,

        'features' => [
            'is_crypto'         => false,
            'supports_checkout' => true,
            'supports_polling'  => true,
            'supports_callback' => false,

            // авто-операции через __call() в AbstractGateway (по config.php)
            'auto_operations'   => true,
        ],

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

        // Приём: создание платежа (реквизиты или редирект)
        'purchase' => [
            'type'           => 'incoming',
            'label'          => 'Создание платежа',
            'description'    => 'Создаёт платёж и возвращает реквизиты или редирект.',
            'request_class'  => PurchaseRequest::class,
            'response_class' => PurchaseResponse::class,
        ],

        // Приём: проверка поступления (polling / API)
        'fetch_payment' => [
            'type'           => 'incoming',
            'label'          => 'Проверка оплаты',
            'description'    => 'Получает статус входящего платежа через API.',
            'request_class'  => FetchPaymentRequest::class,
            'response_class' => FetchPaymentResponse::class,
        ],
    ],

    /*
     * Inputs (UI):
     * Поля конфигурации, которые заполняет админ.
     */
    'inputs' => [

        // MERCHANT (incoming)
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
            ],
        ],

        // PAY (outgoing) — отсутствует в исходном JSON, оставляем пустым
        'pay' => [
            'fields' => [],
            'options_fields' => [],
        ],
    ],
];
