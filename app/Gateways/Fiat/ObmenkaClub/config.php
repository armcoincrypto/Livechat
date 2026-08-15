<?php

use App\Gateways\Fiat\ObmenkaClub\Messages\PayoutRequest;
use App\Gateways\Fiat\ObmenkaClub\Messages\PayoutResponse;
// use App\Gateways\Fiat\ObmenkaClub\Messages\FetchPayoutRequest;
// use App\Gateways\Fiat\ObmenkaClub\Messages\FetchPayoutResponse;

return [

    'schema' => 1,

    'meta' => [
        'name'        => 'Obmenka Club',
        'alias'       => 'obmenkaclub',
        'version'     => '4.0',
        'description' => 'Шлюз Obmenka Club для выплат (импорт из старого конфига).',
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
                'outgoing' => false,
            ],
        ],
    ],

    'operations' => [
        'payout' => [
            'type'           => 'outgoing',
            'label'          => 'Выплата средств',
            'description'    => 'Создаёт выплату через Obmenka Club.',
            'request_class'  => PayoutRequest::class,
            'response_class' => PayoutResponse::class,
        ]
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
                    'type'        => 'input',
                    'label'       => 'API Key',
                    'key'         => 'api_key',
                    'is_hidden'   => true,
                    'value_type'  => 'string',
                    'required'    => true,
                ],
            ],

            'options_fields' => [
                // пока нет дополнительных опций
            ],
        ],
    ],
];
