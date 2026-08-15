<?php

return [
    'error' => [
        'limited_access_direction' => 'You have limited access to this area',
        'holding_direction' => 'Direction frozen',
        'cannot_order_operator' => 'You cannot create a request, contact the operator',
        'cannot_order_hour_operator' => 'Hourly limit for creating applications exceeded, contact your operator',
        'cannot_order_daily_operator' => 'Exceeded the daily limit for creating applications, contact your operator',
        'account_limit_exceeded' => 'Application creation limit exceeded for :account',
        'ip_limit_exceeded' => 'Exceeded the limit for creating applications from one ip address',
        'email_limit_exceeded' => 'The limit for creating applications from one e-mail address is exceeded',
        'order_limit_exceeded' => 'Application creation limit exceeded, contact your operator',
        'not_available_country' => 'Referral prohibited in your country',
        'not_available_device' => 'Direction prohibited for your type of device',
        'credit_card' => 'You have entered the wrong number. Check it and try again',
        'depletedReserve' => 'At this time, the reserve currency is not enough for sharing.',
        'depletedReserveLimited' => 'At this time, the reserve currency is not enough for sharing. If you want to create an application on our website then the application may take about 1 to 2 hours.',
        'min' => 'Too small for the amount of the transfer. The exchange amount must not be less than :min',
        'minReserve' => 'At this time we don\'t need more than :sum for :payment!<br /> Limit exceeded :limit',
        'wrong_wallet' => 'You specified the wrong number of a purse :payment',
        'wrong_wallet_example' => 'You specified the wrong number of a purse :payment. Example: :example',
        'wrong_address' => 'Invalid address :payment',
    ],
    'validator' => [
        'email' => 'Enter the correct E-mail'
    ],

    'network' => [
        'input' => 'Choose a network',
    ],

    'cities' => [
        'input' => 'Choose city',
    ],
];
