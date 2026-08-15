<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => App\Models\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook' => [
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
            'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        ],
    ],

    'telegram-bot-api' => [
        'token' => env('TELEGRAM_BOT_TOKEN', 'YOUR BOT TOKEN HERE'),
        'channel_id' => env('TELEGRAM_CHANNEL_ID', 'DEFAULT'),
        'connect_timeout' => (float) env('TELEGRAM_HTTP_CONNECT_TIMEOUT', 2),
        'timeout' => (float) env('TELEGRAM_HTTP_TIMEOUT', 5),
    ],

    'vkontakte' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => '',
    ],

    'google' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => '',
    ],

    'yandex' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => '',
    ],

    'facebook' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => '',
    ],

    'twitter' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => '',
    ],
];
