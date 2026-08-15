<?php

use App\Gateways\Fiat\Pshb\Messages\CompletePurchaseRequest;
use App\Gateways\Fiat\Pshb\Messages\CompletePurchaseResponse;
use App\Gateways\Fiat\Pshb\Messages\PurchaseRequest;
use App\Gateways\Fiat\Pshb\Messages\PurchaseResponse;

return [
    'schema' => 1,

    'meta' => [
        'name'        => 'Pshb',
        'alias'       => 'pshb',
        'version'     => '1.0',
        'description' => 'Шаблон шлюза Pshb.',
        'category'    => 'fiat',
        'visibility'  => 'private', // public|private

        // UI
        'sorting'     => 100,
        'recommended' => false,
    ],

    'capabilities' => [
        'incoming' => true,
        'outgoing' => false,

        'features' => [
            'is_crypto'         => false,
            'supports_checkout' => true,
            'supports_balance'  => true,

            // polling тут не обязателен (если callback есть)
            'supports_polling'  => false,
            'supports_callback' => true,

            'auto_operations'   => true,
        ],

        'callbacks' => [
            'ipn'        => true,
            'return_url' => true
        ],
    ],

    'operations' => [
        'purchase' => [
            'type'           => 'incoming',
            'label'          => 'Создание платежа',
            'description'    => 'Создаёт платёж и возвращает результат.',
            'request_class'  => PurchaseRequest::class,
            'response_class' => PurchaseResponse::class,
            'supports_redirect' => true,
            'flow' => [
                /**
                 * default  — сразу отдаём реквизиты (account/tag/bankName)
                 * form        — отдаём POST форму (RedirectResponseInterface + method POST + redirectData)
                 * redirect    — отдаём URL (RedirectResponseInterface + method GET + redirectUrl)
                 */
                'mode' => 'default', // default|form|redirect

                /**
                 * Нужно ли сохранять внешний идентификатор (externalId) в MerchantTransactionData.
                 * Обычно true для redirect/form (чтобы потом fetchPayment или completePurchase)
                 * и часто true для requisites (если провайдер выдаёт invoice/payment_id).
                 */
                'store_external_id' => true,

                /**
                 * Если true — после purchase нужно включить polling (fetch_payment),
                 * если провайдер не присылает callback или ты хочешь дублировать проверкой.
                 */
                'supports_polling' => true,
            ],
        ],

        'complete_purchase' => [
            'type'           => 'incoming',
            'label'          => 'Callback (completePurchase)',
            'description'    => 'Обработка IPN/Status URL от Volet SCI.',
            'request_class'  => CompletePurchaseRequest::class,
            'response_class' => CompletePurchaseResponse::class,
            'supports_redirect' => false,
        ],
    ],

    'inputs' => [
        'merchant' => [


            /**
             * Новый, чистый блок описания callback для мерчанта
             *
             * Замена старых ключей:
             * - is_merchant_callback_urls
             * - callback_input_order_id
             * - type_callbacks_route
             * - is_allow_ip_address
             */
            'callback' => [
                'enabled' => true,

                // поле, по которому ты находишь заявку в callback-пейлоаде
                'order_id_field' => 'order_id',

                // Laravel route name (куда ведет callback endpoint)
                'route_name' => 'merchant.receive_money',

                // разрешить проверку IP (whitelist)
                'ip_whitelist_enabled' => true,

                'send_return_urls' => true,
                'success_url_enabled' => true,
                'fail_url_enabled' => true,
            ],

            'fields' => [
                [
                    'type'        => 'input',
                    'label'       => 'Домен',
                    'key'         => 'api_domain',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'ID project',
                    'key'         => 'id_project',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],

                [
                    'type'        => 'input',
                    'label'       => 'Secret Key',
                    'key'         => 'secret_key',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
            ],
            'options_fields' => [
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
                    'label'      => 'Метод оплаты',
                    'key'        => 'method_pay',
                    'options'    => [
                        'sbp'          => 'SBP · RUB (P2P)',
                        'card'         => 'Карты',
                    ],
                    'default'    => 'sbp',
                    'value_type' => 'string',
                    'required'   => false,
                ],
            ],
        ],

        'pay' => [
            'fields' => [
                //
            ],
            'options_fields' => [
            ],
        ],
    ],
];
