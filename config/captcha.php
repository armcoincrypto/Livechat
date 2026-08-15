<?php

return [
    'secret' => env('NOCAPTCHA_SECRET'),
    'sitekey' => env('NOCAPTCHA_SITEKEY'),
    'theme' => 'light',
    'options' => [
        'timeout' => 30,
    ],
];
