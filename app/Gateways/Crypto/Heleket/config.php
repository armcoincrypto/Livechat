<?php

declare(strict_types=1);

use App\Gateways\Crypto\Heleket\Messages\PurchaseRequest;
use App\Gateways\Crypto\Heleket\Messages\PurchaseResponse;
use App\Gateways\Crypto\Heleket\Messages\FetchPaymentRequest;
use App\Gateways\Crypto\Heleket\Messages\FetchPaymentResponse;

use App\Gateways\Crypto\Heleket\Messages\PayoutRequest;
use App\Gateways\Crypto\Heleket\Messages\PayoutResponse;
use App\Gateways\Crypto\Heleket\Messages\FetchPayoutRequest;
use App\Gateways\Crypto\Heleket\Messages\FetchPayoutResponse;

return [

    'schema' => 1,

    'meta' => [
        'name'        => 'Heleket',
        'alias'       => 'heleket',
        'version'     => '4.0',
        'description' => 'Шлюз Heleket для крипто-платежей и выплат.',
        'category'    => 'crypto',

        // UI
        'sorting'     => 0,
        'recommended' => true,
        'group'       => 'Рекомендуемые',
    ],

    'capabilities' => [
        'incoming' => true,
        'outgoing' => true,

        'features' => [
            'is_crypto'         => true,
            'supports_checkout' => true,   // есть режим редиректа на платёжную форму
            'supports_polling'  => true,   // check_payment=true в старом
            'supports_balance'  => false,
            'auto_operations'   => true,
        ],

        'callbacks' => [
            // если у тебя реально есть callback/IPN — поставь true и добавь inputs.merchant.callback
            'ipn'        => false,
            'return_url' => false,
            'cancel_url' => false,
        ],
    ],

    'operations' => [
        'purchase' => [
            'type'           => 'incoming',
            'label'          => 'Создание платежа',
            'description'    => 'Создаёт платёж (редирект или выдача адреса) в Heleket.',
            'request_class'  => PurchaseRequest::class,
            'response_class' => PurchaseResponse::class,
        ],

        'fetch_payment' => [
            'type'           => 'incoming',
            'label'          => 'Проверка оплаты (polling)',
            'description'    => 'Проверяет статус входящего платежа через API Heleket.',
            'request_class'  => FetchPaymentRequest::class,
            'response_class' => FetchPaymentResponse::class,
        ],

        'payout' => [
            'type'           => 'outgoing',
            'label'          => 'Выплата средств',
            'description'    => 'Создаёт выплату в Heleket.',
            'request_class'  => PayoutRequest::class,
            'response_class' => PayoutResponse::class,
        ],

        'fetch_payout' => [
            'type'           => 'outgoing',
            'label'          => 'Проверка выплаты (polling)',
            'description'    => 'Проверяет статус выплаты через API Heleket.',
            'request_class'  => FetchPayoutRequest::class,
            'response_class' => FetchPayoutResponse::class,
        ],

        // если для UI нужно options_api_method — добавь опцию "options" (service)
        // 'options' => [
        //     'type'           => 'service',
        //     'label'          => 'Options API',
        //     'description'    => 'Получение списков для select-полей (UI).',
        //     'request_class'  => OptionsRequest::class,
        //     'response_class' => OptionsResponse::class,
        // ],
    ],

    'inputs' => [

        'merchant' => [

            // Если у Heleket есть callback — раскомментируй и заполни:
            // 'callback' => [
            //     'enabled'              => true,
            //     'order_id_field'       => 'externalId',
            //     'route_name'           => 'merchant.receive_money',
            //     'ip_whitelist_enabled' => true,
            // ],

            'fields' => [
                [
                    'type'       => 'input',
                    'label'      => 'Merchant ID',
                    'key'        => 'merchant_id',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
                [
                    'type'       => 'input',
                    'label'      => 'Payment API key',
                    'key'        => 'secret_key',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
            ],

            'options_fields' => [
                [
                    'type'      => 'select',
                    'label'     => 'Тип оплаты',
                    'key'       => 'type_method_receiving_pay',
                    'options'   => [
                        '0' => 'Переадресация на (платежную форму)',
                        '1' => 'Выдача адреса',
                    ],
                    'default'   => 0,
                    'value_type'=> 'int',
                    'required'  => false,
                ],
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
                    'type'       => 'input',
                    'label'      => 'Код валюты (network code)',
                    'key'        => 'network_code_currency',
                    'value_type' => 'string',
                    'required'   => false,
                ],
            ],
        ],

        'pay' => [
            'fields' => [
                [
                    'type'       => 'input',
                    'label'      => 'Merchant ID',
                    'key'        => 'merchant_id',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
                [
                    'type'       => 'input',
                    'label'      => 'Api Key',
                    'key'        => 'secret_key',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
                [
                    'type'       => 'input',
                    'label'      => 'Payout Key',
                    'key'        => 'payout_key',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
            ],

            'options_fields' => [
                [
                    'type'       => 'select',
                    'label'      => 'Приоритет',
                    'key'        => 'priority',
                    'options'    => [
                        'recommended' => 'recommended',
                        'economy'     => 'economy',
                        'high'        => 'high',
                        'highest'     => 'highest',
                    ],
                    'default'    => 'recommended',
                    'value_type' => 'string',
                    'required'   => false,
                ],
                [
                    'type'       => 'select',
                    'label'      => 'Комиссия за вывод средств',
                    'key'        => 'is_subtract',
                    'options'    => [
                        '0' => 'С баланса',
                        '1' => 'С суммы выплаты',
                    ],
                    'default'    => 0,
                    'value_type' => 'int',
                    'required'   => false,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'Код валюты (конвертацию)',
                    'key'         => 'currency_code',
                    'description' => 'Используйте код валюты, если хотите выплачивать через конвертер Fiat -> Coin. Доступен: USDT',
                    'value_type'  => 'string',
                    'required'    => false,
                ],
            ],
        ],
    ],
];
