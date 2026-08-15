<?php

use App\Gateways\Fiat\NicePay\Messages\CompletePurchaseRequest;
use App\Gateways\Fiat\NicePay\Messages\CompletePurchaseResponse;
use App\Gateways\Fiat\NicePay\Messages\PurchaseRequest;
use App\Gateways\Fiat\NicePay\Messages\PurchaseResponse;

return [
    'schema' => 1,

    'meta' => [
        'name'        => 'NicePay',
        'alias'       => 'nicepay',
        'version'     => '1.0',
        'description' => 'Шаблон шлюза NicePay. Заполни описание.',
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
                    'label'       => 'ID мерчанта',
                    'key'         => 'id_merchant_key',
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
                        'auto'                  => 'Автоматический выбор',
                        'sbp_rub'               => 'SBP · RUB (P2P)',
                        'sberbank_rub'          => 'Sberbank → карта · RUB (P2P)',
                        'sberbank_account_rub'  => 'Sberbank → счёт · RUB (P2P)',
                        'tinkoff_rub'           => 'Tinkoff Bank · RUB (P2P)',
                        'alfabank_rub'          => 'Alfabank · RUB (P2P)',
                        'raiffeisen_rub'        => 'Raiffeisen · RUB (P2P)',
                        'vtb_rub'               => 'VTB Bank · RUB (P2P)',
                        'rnkbbank_rub'          => 'RNKB Bank · RUB (P2P)',
                        'postbank_rub'          => 'Pochta Bank · RUB (P2P)',
                        'yoomoney_rub'          => 'YooMoney · RUB (P2P)',

                        'monobank_uah'          => 'Monobank · UAH (P2P)',
                        'privatbank_uah'        => 'PrivatBank · UAH (P2P)',
                        'raiffeisen_uah'        => 'Raiffeisen · UAH (P2P)',

                        'advcash_kzt'           => 'AdvCash · KZT (P2P)',
                        'kaspibank_kzt'         => 'Kaspi Bank · KZT (P2P)',
                        'halykbank_kzt'         => 'Halyk Bank · KZT (P2P)',
                        'jysanbank_kzt'         => 'Jysan Bank · KZT (P2P)',
                        'centercreditbank_kzt'  => 'CenterCredit Bank · KZT (P2P)',
                        'fortebank_kzt'         => 'ForteBank · KZT (P2P)',
                    ],
                    'default'    => 'card',
                    'value_type' => 'string',
                    'required'   => false,
                ],
            ],
        ],

        'pay' => [
            'fields' => [
                // payout-параметры (если шлюз поддерживает outgoing)
            ],
            'options_fields' => [
            ],
        ],
    ],
];
