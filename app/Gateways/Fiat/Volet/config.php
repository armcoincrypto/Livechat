<?php

declare(strict_types=1);

use App\Gateways\Fiat\Volet\Messages\PurchaseRequest;
use App\Gateways\Fiat\Volet\Messages\PurchaseResponse;
use App\Gateways\Fiat\Volet\Messages\CompletePurchaseRequest;
use App\Gateways\Fiat\Volet\Messages\CompletePurchaseResponse;
use App\Gateways\Fiat\Volet\Messages\PayoutRequest;
use App\Gateways\Fiat\Volet\Messages\PayoutResponse;

return [

    'schema' => 1,

    'meta' => [
        'name'        => 'Volet (ADVCash)',
        'alias'       => 'volet',
        'version'     => '4.0',
        'description' => 'Volet / ADVCash SCI (оплата через POST-форму) + callback (completePurchase).',
        'category'    => 'fiat',

        // UI
        'sorting'     => 100,
        'recommended' => false,
        'group'       => '',
    ],

    'capabilities' => [
        'incoming' => true,
        'outgoing' => true,

        'features' => [
            'is_crypto'         => false,
            'supports_checkout' => true,
            'supports_balance'  => true,

            // polling тут не обязателен (если callback есть)
            'supports_polling'  => false,
            'supports_callback' => true,

            'auto_operations'   => true,
        ],

        // общая декларация, что callback-поддержка есть (для UI/маршрутов)
        'callbacks' => [
            'ipn'        => true,
            'return_url' => true,
            'cancel_url' => true,
        ],
    ],

    'operations' => [
        // Создание платежа (SCI form POST redirect)
        'purchase' => [
            'type'           => 'incoming',
            'label'          => 'Создание платежа (SCI форма)',
            'description'    => 'Формирует POST-форму Volet SCI и делает redirect на account.volet.com.',
            'request_class'  => PurchaseRequest::class,
            'response_class' => PurchaseResponse::class,
            'supports_redirect' => true,
        ],

        // Callback/IPN от Volet (статус платежа)
        'complete_purchase' => [
            'type'           => 'incoming',
            'label'          => 'Callback (completePurchase)',
            'description'    => 'Обработка IPN/Status URL от Volet SCI.',
            'request_class'  => CompletePurchaseRequest::class,
            'response_class' => CompletePurchaseResponse::class,
            'supports_redirect' => false,
        ],

        // Выплаты (если используешь)
        'payout' => [
            'type'           => 'outgoing',
            'label'          => 'Выплата',
            'description'    => 'Создание выплаты через API Volet/ADVCash.',
            'request_class'  => PayoutRequest::class,
            'response_class' => PayoutResponse::class,
            'supports_redirect' => false,
        ],
    ],

    'inputs' => [

        'merchant' => [
            /**
             * Новый, чистый блок callback (замена старых ключей):
             * - is_merchant_callback_urls
             * - callback_input_order_id
             * - type_callbacks_route
             * - is_allow_ip_address
             */
            'callback' => [
                'enabled' => true,

                // поле, по которому ты будешь искать заявку в payload callback
                // старое: callback_input_order_id = ac_order_id
                'order_id_field' => 'ac_order_id',

                // laravel route name (куда прилетает callback)
                // старое: type_callbacks_route = merchant.receive_money
                'route_name' => 'merchant.receive_money',

                // включать ли проверку IP whitelist
                // старое: is_allow_ip_address = true
                'ip_whitelist_enabled' => true,

                /**
                 * Нужно ли формировать success/fail urls и отправлять их в gateway.
                 * Для SCI формы — обычно да.
                 */
                'send_return_urls' => true,

                // эти 2 флага заменяют старые is_callback_without_success/is_callback_without_failed
                'success_url_enabled' => true,
                'fail_url_enabled'    => true,
            ],

            'fields' => [
                [
                    'type'       => 'input',
                    'label'      => 'E-mail аккаунта (SCI)',
                    'key'        => 'sci_account_email',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
                [
                    'type'       => 'input',
                    'label'      => 'Название SCI',
                    'key'        => 'sci_name',
                    'is_hidden'  => false,
                    'value_type' => 'string',
                    'required'   => true,
                ],
                [
                    'type'       => 'input',
                    'label'      => 'SCI Пароль',
                    'key'        => 'sci_password',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
                [
                    'type'       => 'input',
                    'label'      => 'Название API',
                    'key'        => 'api_name',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => false,
                ],
                [
                    'type'       => 'input',
                    'label'      => 'Пароль от API',
                    'key'        => 'api_secret',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => false,
                ],
            ],

            'options_fields' => [
                [
                    'type'       => 'select',
                    'label'      => 'Проверить на совпадение номера счета',
                    'key'        => 'is_from_check_wallet',
                    'options'    => [
                        '0' => 'Нет',
                        '1' => 'Да',
                    ],
                    'default'    => '0',
                    'value_type' => 'int',
                    'required'   => false,
                ],
            ],
        ],

        'pay' => [
            'fields' => [
                [
                    'type'       => 'input',
                    'label'      => 'E-mail аккаунта (API)',
                    'key'        => 'api_account_email',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
                [
                    'type'       => 'input',
                    'label'      => 'Название API',
                    'key'        => 'api_name',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
                [
                    'type'       => 'input',
                    'label'      => 'Пароль от API',
                    'key'        => 'api_secret',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
            ],

            'options_fields' => [
                [
                    'type'       => 'select',
                    'label'      => 'Тип транзакции',
                    'key'        => 'type_transaction',
                    'options'    => [
                        'wallet' => 'Кошелек',
                        'email'  => 'E-mail',
                    ],
                    'default'    => 'wallet',
                    'value_type' => 'string',
                    'required'   => false,
                ],
            ],
        ],
    ],
];
