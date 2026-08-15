<?php

return [
    'master_key' => env('VAULT_MASTER_KEY', ''),
    'storage_path' => storage_path('app/vault'),

    'gateway_options' => storage_path('/app/gateway-options')
];
