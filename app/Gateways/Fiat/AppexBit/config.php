<?php

return [

    'schema' => 1,

    'meta' => [
        'name'        => 'AppexBit',
        'alias'       => 'appexbit',
        'version'     => '4.0',
        'description' => 'Шлюз AppexBit (импорт из старого JSON).',
        'category'    => 'fiat',

        'sorting'     => 100,
        'recommended' => false,
    ],

    'capabilities' => [
        'incoming' => true,
        'outgoing' => false,

        'features' => [
            'is_crypto'         => false,
            'supports_balance'  => false,
            'supports_polling'  => true, // прием средств
            'supports_callback' => false,

            // если хочешь авто-операции через __call()
            'auto_operations'   => true,
        ],

        'callbacks' => [
            'ipn'        => false,
            'return_url' => false,
            'cancel_url' => false,
        ],
    ],

    /**
     * Операции
     * (классы подставишь после генерации Requests/Responses)
     */
    'operations' => [
        'purchase' => [
            'type'           => 'incoming',
            'label'          => 'Создание платежа',
            'description'    => 'Создание платежа (checkout).',
            'request_class'  => \App\Gateways\Fiat\AppexBit\Messages\PurchaseRequest::class,
            'response_class' => \App\Gateways\Fiat\AppexBit\Messages\PurchaseResponse::class,
        ],

         'complete_purchase' => [
             'type'           => 'incoming',
             'label'          => 'Callback (IPN)',
             'description'    => 'Обработка callback от AppexBit.',
             'request_class'  => \App\Gateways\Fiat\AppexBit\Messages\CompletePurchaseRequest::class,
             'response_class' => \App\Gateways\Fiat\AppexBit\Messages\CompletePurchaseResponse::class,
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
                'order_id_field' => 'externalId',

                // Laravel route name (куда ведет callback endpoint)
                'route_name' => 'merchant.receive_money',

                // разрешить проверку IP (whitelist)
                'ip_whitelist_enabled' => true,


                'send_return_urls' => true,
                'success_url_enabled' => true,
                'fail_url_enabled' => true,

                // сюда позже можно добавить:
                // 'ip_whitelist' => ['1.2.3.4', '5.6.7.8'],
            ],

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
                    'type'        => 'select',
                    'label'       => 'Способ приема оплаты',
                    'key'         => 'type_pay',
                    'options'     => [
                        '0' => 'Карты',
                        '1' => 'SPB',
                        '2' => 'Номер счета',
                        '3' => 'Любой',
                    ],
                    'value_type'  => 'string',
                    'required'    => false,
                ],
                [
                    'type'        => 'select',
                    'label'       => 'Название банка',
                    'key'         => 'bank_name',
                    'options_file'=> 'appexbit.json',
                    'value_type'  => 'string',
                    'required'    => false,
                ],
            ],
        ],

        // pay отсутствует, т.к. is_pay=0
        'pay' => [
            'fields' => [],
            'options_fields' => [],
        ],
    ],
];
