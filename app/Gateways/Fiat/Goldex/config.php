<?php

use App\Gateways\Fiat\Goldex\Messages\OptionsRequest;
use App\Gateways\Fiat\Goldex\Messages\OptionsResponse;
use App\Gateways\Fiat\Goldex\Messages\PayoutRequest;
use App\Gateways\Fiat\Goldex\Messages\PayoutResponse;
use App\Gateways\Fiat\Goldex\Messages\FetchPayoutRequest;
use App\Gateways\Fiat\Goldex\Messages\FetchPayoutResponse;

return [

    'schema' => 1,

    'meta' => [
        'name'        => 'Goldex',
        'alias'       => 'goldex',
        'version'     => '4.0',
        'description' => 'Шлюз Goldex для выплат (импорт из старого конфига).',
        'category'    => 'fiat',

        // UI
        'sorting'     => 100,
        'recommended' => false,
    ],

    'capabilities' => [
        'incoming' => false,
        'outgoing' => true,

        'features' => [
            'supports_callback' => false,
            'auto_operations'   => true,

            // polling выплат — выключен (можно включить позже)
            'polling' => [
                'incoming' => false,
                'outgoing' => true,
            ],
        ],
    ],

    'operations' => [
        'payout' => [
            'type'           => 'outgoing',
            'label'          => 'Выплата средств',
            'description'    => 'Создаёт выплату через Goldex.',
            'request_class'  => PayoutRequest::class,
            'response_class' => PayoutResponse::class,
        ],

        'options' => [
            'type' => 'service',
            'label' => 'Options API',
            'request_class' => OptionsRequest::class,
            'response_class' => OptionsResponse::class,
        ],
    ],

    'inputs' => [

        // merchant отсутствует — это pay-only шлюз
        'merchant' => [
            'fields' => [],
            'options_fields' => [],
        ],

        'pay' => [
            'fields' => [
                [
                    'type'       => 'input',
                    'label'      => 'API Key',
                    'key'        => 'api_key',
                    'is_hidden'  => true,
                    'value_type' => 'string',
                    'required'   => true,
                ],
            ],

            'options_fields' => [
                [
                    'type'               => 'select',
                    'label'              => 'Метод выплаты',
                    'key'                => 'bank_name',
                    'options_api_method' => 'getExchangeRate',
                    'default'            => '',
                    'value_type'         => 'string',
                    'required'           => false,
                ],
            ],
        ],
    ],
];
