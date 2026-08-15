<?php

use App\Gateways\Crypto\Rapira\Messages\FetchPaymentRequest;
use App\Gateways\Crypto\Rapira\Messages\FetchPaymentResponse;
use App\Gateways\Crypto\Rapira\Messages\FetchPayoutRequest;
use App\Gateways\Crypto\Rapira\Messages\FetchPayoutResponse;
use App\Gateways\Crypto\Rapira\Messages\HealthRequest;
use App\Gateways\Crypto\Rapira\Messages\HookOrderCompletedRequest;
use App\Gateways\Crypto\Rapira\Messages\HookOrderRejectedRequest;
use App\Gateways\Crypto\Rapira\Messages\PayoutRequest;
use App\Gateways\Crypto\Rapira\Messages\PayoutResponse;
use App\Gateways\Crypto\Rapira\Messages\PurchaseRequest;
use App\Gateways\Crypto\Rapira\Messages\PurchaseResponse;
use iEXPackages\Payments\Core\Engine\HealthResponse;
use iEXPackages\Payments\Core\Engine\HookResponse;

return [

    'schema' => 1,

    // Базовая информация о шлюзе
    'meta' => [
        'name'        => 'Rapira Crypto',
        'alias'       => 'rapira',
        'version'     => '4.0',
        'description' => 'Шлюз Rapira для приёма крипто-платежей.',
        'category'    => 'crypto',

        // UI/маркетинг
        'sorting'     => 1,
        'recommended' => true,
    ],

    // Возможности шлюза (без дублирования operations)
    'capabilities' => [
        'incoming' => true,
        'outgoing' => true,

        'features' => [
            'auto_operations' => true,
            'is_crypto'         => true,
            'supports_polling'  => true,  // fetch_payment / fetch_payout
            'supports_callback' => false,  // если реально есть callback (complete_purchase)
        ],
    ],

    // Источник правды по операциям — только здесь
    'operations' => [
        'purchase' => [
            'type'             => 'incoming',
            'label'            => 'Создание платежа',
            'description'      => 'Создаёт платёж и возвращает результат.',
            'request_class'    => PurchaseRequest::class,
            'response_class'   => PurchaseResponse::class,
            'supports_redirect'=> false,
        ],

        'fetch_payment' => [
            'type'           => 'incoming',
            'label'          => 'Проверка оплаты (polling)',
            'description'    => 'Получает статус входящего платежа через API.',
            'request_class'  => FetchPaymentRequest::class,
            'response_class' => FetchPaymentResponse::class,
        ],

        'hook_order_completed' => [
            'type'           => 'service',
            'label'          => 'Hook: order completed',
            'description'    => 'Выполняется после успешного завершения заявки.',
            'request_class'  => HookOrderCompletedRequest::class,
            'response_class' => HookResponse::class,
        ],

        'hook_order_rejected' => [
            'type'           => 'service',
            'label'          => 'Hook: order rejected',
            'description'    => 'Выполняется после отклонения заявки.',
            'request_class'  => HookOrderRejectedRequest::class,
            'response_class' => HookResponse::class,
        ],

        'payout' => [
            'type'             => 'outgoing',
            'label'            => 'Выплата средств',
            'description'      => 'Создаёт выплату в шлюзе и возвращает результат.',
            'request_class'    => PayoutRequest::class,
            'response_class'   => PayoutResponse::class,
            'supports_redirect'=> false,
        ],

        'fetch_payout' => [
            'type'           => 'outgoing',
            'label'          => 'Проверка выплаты (polling)',
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
     */
    'inputs' => [

        // Incoming (GatewayMerchant)
        'merchant' => [
            'callback_input_order_id' => 'order_id',

            'fields' => [
                [
                    'type'        => 'input',
                    'label'       => 'Private Key',
                    'key'         => 'private_key',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'UID',
                    'key'         => 'uuid',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
                [
                    'type'        => 'select',
                    'label'       => 'API Host',
                    'key'         => 'api_host',
                    'options'     => [
                        'api.rapira.net' => 'api.rapira.net',
                        'api.rapira.org' => 'api.rapira.org',
                    ],
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
                [
                    'type'        => 'input',
                    'label'       => 'Код валюты сети (network code)',
                    'key'         => 'network_code_currency',
                    'value_type'  => 'string',
                    'required'    => false,
                ],
            ],
        ],

        // Outgoing (GatewayPayment)
        'pay' => [
            'fields' => [
                [
                    'type'        => 'input',
                    'label'       => 'Private Key',
                    'key'         => 'private_key',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'UID',
                    'key'         => 'uuid',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
                [
                    'type'        => 'select',
                    'label'       => 'API Host',
                    'key'         => 'api_host',
                    'options'     => [
                        'api.rapira.net' => 'api.rapira.net',
                        'api.rapira.org' => 'api.rapira.org',
                    ],
                    'value_type'  => 'string',
                    'required'    => true,
                ],
            ],

            'options_fields' => [
                [
                    'type'        => 'select',
                    'label'       => 'Разрешить конвертацию?',
                    'key'         => 'conversion_enabled',
                    'options'     => [
                        '0' => 'Нет',
                        '1' => 'Да',
                    ],
                    'value_type'  => 'bool',
                    'required'    => false,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'Валюта из которой будет конвертация',
                    'key'         => 'convert_from_currency',
                    'default'     => 'USDT',
                    'value_type'  => 'string',
                    'required'    => false,
                ],
                [
                    'type'        => 'select',
                    'label'       => 'Не конвертировать, если достаточно на балансе указанной к выводу валюты в coin',
                    'key'         => 'enough_coin_payment',
                    'options'     => [
                        '0' => 'Нет',
                        '1' => 'Да',
                    ],
                    'value_type'  => 'bool',
                    'required'    => false,
                ],
            ],
        ],
    ],
];
