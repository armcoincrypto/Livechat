<?php

use App\Models\BestChangeDirection;
use App\Models\Currency;
use Illuminate\Support\Str;

return function () {
    duplicateAccountFieldsForAllCurrencies();
};

function duplicateAccountFieldsForAllCurrencies()
{
    $currencies = Currency::all();
    $countUpdated = 0;

    foreach ($currencies as $currency) {
        $updated = false;

        if (!empty($currency->mask_account_from)) {
            $currency->mask_account_to = $currency->mask_account_from;
            $updated = true;
        }

        if (!empty($currency->validation_account_from)) {
            $currency->validation_account_to = $currency->validation_account_from;
            $updated = true;
        }

        if ($updated) {
            $currency->save();
            $countUpdated++;
        }
    }

    return $countUpdated;
}
