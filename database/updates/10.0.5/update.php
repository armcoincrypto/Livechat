<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return function () {

    Schema::dropIfExists('proxies_payment');
    Schema::dropIfExists('proxies_qiwi');
    Schema::dropIfExists('qiwi_currencies');
};
