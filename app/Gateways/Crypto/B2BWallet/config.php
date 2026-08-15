<?php

use App\Gateways\Crypto\B2BWallet\Messages\FetchPaymentRequest;
use App\Gateways\Crypto\B2BWallet\Messages\FetchPaymentResponse;
use App\Gateways\Crypto\B2BWallet\Messages\FetchPayoutRequest;
use App\Gateways\Crypto\B2BWallet\Messages\FetchPayoutResponse;
use App\Gateways\Crypto\B2BWallet\Messages\PayoutRequest;
use App\Gateways\Crypto\B2BWallet\Messages\PayoutResponse;
use App\Gateways\Crypto\B2BWallet\Messages\PurchaseRequest;
use App\Gateways\Crypto\B2BWallet\Messages\PurchaseResponse;

return [

    'schema' => 1,

    'meta' => [
        'name'        => 'B2BWallet',
        'alias'       => 'b2bwallet',
        'version'     => '4.0',
        'description' => 'Шлюз B2BWallet (импорт из старого JSON).',
        'category'    => 'crypto',

        // необязательно
        'sorting'     => 100,
        'recommended' => false,
    ],

    'capabilities' => [
        // parameters: is_merchant / is_pay
        'incoming' => true,
        'outgoing' => true,

        // payment_options
        'features' => [
            'is_crypto'         => true,
            'supports_balance'  => true,
            'supports_polling'  => true,   // check_payment=true
            'supports_callback' => false,   // ipn_status_url=true
            'auto_operations'   => true,   // если хочешь авто-методы через __call()
        ],

        // если ipn_status_url=true — значит callback доступен
        'callbacks' => [
            'ipn'        => false,
            'return_url' => false,
            'cancel_url' => false,
        ],
    ],

    /**
     * Операции.
     *
     * Тут классы нужно будет подставить, когда ты создашь Messages/*Request/*Response.
     * Я оставил как шаблонные строки/комментарии.
     */
    'operations' => [
        'purchase' => [
            'type'           => 'incoming',
            'label'          => 'Создание платежа',
            'description'    => 'Создание платежа (incoming).',
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
            'description'    => 'Создание выплаты (outgoing).',
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
                    'type'        => 'input',
                    'label'       => 'Мин. кол-во подтверждений',
                    'key'         => 'min_confirm',
                    'default'     => 0,
                    'value_type'  => 'int',
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
                // в твоём JSON нет options_fields для pay — оставляю пустым
            ],
        ],
    ],
];
