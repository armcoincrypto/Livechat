<?php

use App\Gateways\Fiat\BestMerchant\Messages\PurchaseRequest;
use App\Gateways\Fiat\BestMerchant\Messages\PurchaseResponse;
use App\Gateways\Fiat\BestMerchant\Messages\FetchPaymentRequest;
use App\Gateways\Fiat\BestMerchant\Messages\FetchPaymentResponse;

return [

    'schema' => 1,

    'meta' => [
        'name'        => 'BestMerchant',
        'alias'       => 'bestmerchant',
        'version'     => '4.0',
        'description' => 'Шлюз BestMerchant (импорт из старого JSON).',
        'category'    => 'merchant',

        // UI (опционально)
        'sorting'     => 100,
        'recommended' => false,
    ],

    'capabilities' => [
        'incoming' => true,
        'outgoing' => false,

        'features' => [
            'polling' => [
                'incoming' => true,
                'outgoing' => false,
            ],
            // можно включить авто-операции через __call()
            'auto_operations' => true,
        ],
    ],

    'operations' => [
        'purchase' => [
            'type'           => 'incoming',
            'label'          => 'Создание платежа',
            'description'    => 'Создаёт платёж и возвращает реквизиты/результат.',
            'request_class'  => PurchaseRequest::class,
            'response_class' => PurchaseResponse::class,
        ],

        'fetch_payment' => [
            'type'           => 'incoming',
            'label'          => 'Проверка оплаты (polling)',
            'description'    => 'Получает статус входящего платежа через API.',
            'request_class'  => FetchPaymentRequest::class,
            'response_class' => FetchPaymentResponse::class,
        ],
    ],

    'inputs' => [
        'merchant' => [
            'fields' => [
                [
                    'type'        => 'input',
                    'label'       => 'API Token',
                    'key'         => 'api_token',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
            ],

            'options_fields' => [
                [
                    'type'        => 'input',
                    'label'       => 'Мин. время ожидании поступлений (в мин.)',
                    'key'         => 'max_time_register_in_network',
                    'default'     => 30,
                    'value_type'  => 'int',
                    'required'    => false,
                ],
                [
                    'type'        => 'input',
                    'label'       => 'Макс. время ожидании поступлений (в часах.)',
                    'key'         => 'max_time_confirm_in_network',
                    'default'     => 2,
                    'value_type'  => 'int',
                    'required'    => false,
                ],
            ],
        ],

        // pay отсутствует, потому что is_pay не было
        'pay' => [
            'fields' => [],
            'options_fields' => [],
        ],
    ],
];
