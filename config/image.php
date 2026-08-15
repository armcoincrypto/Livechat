<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Image Driver
    |--------------------------------------------------------------------------
    |
    | Intervention Image supports “GD Library” and “Imagick” to process images
    | internally. Depending on your PHP setup, you can choose one of them.
    |
    | Included options:
    |   - \Intervention\Image\Drivers\Gd\Driver::class
    |   - \Intervention\Image\Drivers\Imagick\Driver::class
    |
    */

    'driver' => \Intervention\Image\Drivers\Imagick\Driver::class,


    'resizes' => [
        'card_verification' => 1600,
        'order_check' => 1080,
        'card_verification_preview' => 500,


        'user_verification' => 1600,
        'user_verification_preview' => 500,
    ],


    'folders' => [
        'payment_systems' => 'storage/payment_systems',
        'user_verification' => 'user_verification',
        'news' => 'storage/news',
        'contact' => 'storage/contact',
        'link_review' => 'storage/links'
    ]
];
