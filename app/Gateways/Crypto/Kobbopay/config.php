<?php

declare(strict_types=1);

use App\Gateways\Crypto\Kobbopay\Messages\FetchPaymentRequest;
use App\Gateways\Crypto\Kobbopay\Messages\FetchPaymentResponse;
use App\Gateways\Crypto\Kobbopay\Messages\HealthRequest;
use App\Gateways\Crypto\Kobbopay\Messages\OptionsRequest;
use App\Gateways\Crypto\Kobbopay\Messages\OptionsResponse;
use App\Gateways\Crypto\Kobbopay\Messages\PurchaseRequest;
use App\Gateways\Crypto\Kobbopay\Messages\PurchaseResponse;
use iEXPackages\Payments\Core\Engine\HealthResponse;

return [

    'schema' => 1,

    'meta' => [
        'name'        => 'Kobbopay / Pay.kobbex',
        'alias'       => 'kobbopay',
        'version'     => '1.0',
        'description' => 'Шлюз Kobbopay для приёма криптовалют (Exnode-compatible API).',
        'category'    => 'crypto',

        'sorting'     => 0,
        'recommended' => false,
        'group'       => '',
    ],

    'capabilities' => [
        'incoming' => true,
        'outgoing' => false,

        'features' => [
            'is_crypto'         => true,
            'supports_balance'  => true,
            'supports_polling'  => true,
            // Inbound webhooks handled by KobbopayInboundWebhookService on
            // /callbacks/v1/webhook/kobbopay — polling remains the reconciliation fallback.
            'supports_callback' => true,
            'auto_operations'   => true,
        ],

        'callbacks' => [
            'ipn'        => true,
            'return_url' => false,
            'cancel_url' => false,
        ],
    ],

    'operations' => [
        'purchase' => [
            'type'           => 'incoming',
            'label'          => 'Создание платежа',
            'description'    => 'Создаёт входящий платёж/адрес в Kobbopay.',
            'request_class'  => PurchaseRequest::class,
            'response_class' => PurchaseResponse::class,
        ],

        'fetch_payment' => [
            'type'           => 'incoming',
            'label'          => 'Проверка оплаты (polling)',
            'description'    => 'Получает статус входящего платежа через API Kobbopay.',
            'request_class'  => FetchPaymentRequest::class,
            'response_class' => FetchPaymentResponse::class,
        ],

        'options' => [
            'type'           => 'service',
            'label'          => 'Options API',
            'description'    => 'Списки значений для select-полей UI.',
            'request_class'  => OptionsRequest::class,
            'response_class' => OptionsResponse::class,
        ],

        'health' => [
            'type'           => 'service',
            'label'          => 'Проверка доступности шлюза',
            'description'    => 'Минимальный health-check без побочных эффектов.',
            'request_class'  => HealthRequest::class,
            'response_class' => HealthResponse::class,
        ],
    ],

    'inputs' => [

        'merchant' => [
            'fields' => [
                [
                    'type'        => 'input',
                    'label'       => 'API private signing key',
                    'key'         => 'private_key',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                    'purpose'     => 'Signs outbound Exswaping → Kobbopay API requests (HMAC-SHA512). Not the webhook secret.',
                    'warning'     => 'Changing this affects invoice creation immediately.',
                ],
                [
                    'type'        => 'input',
                    'label'       => 'API public identifier',
                    'key'         => 'public_key',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                    'purpose'     => 'ApiPublic header for outbound Exswaping → Kobbopay API authentication.',
                    'warning'     => 'Changing this affects invoice creation immediately.',
                ],
                [
                    'type'        => 'input',
                    'label'       => 'API base URL',
                    'key'         => 'api_base_url',
                    'default'     => 'https://merchant.kobbex.com',
                    'value_type'  => 'string',
                    'required'    => false,
                    'purpose'     => 'Kobbopay backend API host for invoice/create (not pay.kobbex.com checkout frontend).',
                    'warning'     => 'Changing this affects invoice creation immediately.',
                ],
                [
                    'type'        => 'input',
                    'label'       => 'Inbound webhook signing secret',
                    'key'         => 'webhook_secret',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => false,
                    'purpose'     => 'Verifies inbound Kobbopay → Exswaping callbacks (HMAC-SHA256). Not used for outbound API signing.',
                    'warning'     => 'Outbound API credentials remain unchanged when only this field is updated.',
                ],
            ],

            /*
             * Callback metadata for admin URL generation / documentation.
             * Actual inbound verification is KobbopayInboundWebhookService (raw body + X-Kobbopay-*).
             */
            'callback' => [
                'enabled'              => true,
                'external_id_field'    => 'paymentId',
                'order_id_field'       => 'orderId',
                'route_name'           => 'merchant.webhook',
                'ip_whitelist_enabled' => false,
                'send_return_urls'     => false,
                'success_url_enabled'  => false,
                'fail_url_enabled'     => false,
            ],

            'options_fields' => [
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
                    'type'               => 'select',
                    'label'              => 'Код валюты (network code)',
                    'key'                => 'network_code_currency',
                    'options_api_method' => 'getCurrencies',
                    'value_type'         => 'string',
                    'required'           => false,
                ],
            ],
        ],
    ],
];
