<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/healthz', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'healthyspine-livechat',
    ]);
});
