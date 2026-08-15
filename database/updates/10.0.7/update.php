<?php

use App\Models\BestChangeDirection;
use App\Models\Currency;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return function () {
    $decimalPlaces = (int) iEXSetting('display_decimal_places') ?: 10;
    iEXSetting(['display_decimal_places' => $decimalPlaces]);

    Schema::dropIfExists('settings');
};
