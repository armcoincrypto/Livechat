<?php

use App\Models\Currency;


if (!function_exists('migrateOldCurrencyFilters')) {
    /**
     * Переносит данные из старого поля id_filter_currency в pivot currency_filter.
     * После привязки обнуляет старое поле.
     */
    function migrateOldCurrencyFilters(): void
    {
        $totalMigrated = 0;

        Currency::query()
            ->whereNotNull('id_filter_currency')
            ->chunkById(50, function ($currencies) use (&$totalMigrated) {
                foreach ($currencies as $currency) {
                    $filterId = (int) $currency->id_filter_currency;

                    if ($filterId > 0) {
                        // Добавляем связь в pivot, не удаляя существующие
                        $currency->filters()->syncWithoutDetaching([$filterId]);

                        // Обнуляем старое поле
                        $currency->update(['id_filter_currency' => null]);

                        $totalMigrated++;
                    }
                }
            });

        echo "Перенос завершён. Обработано {$totalMigrated} валют.\n";
    }
}


return function () {
    migrateOldCurrencyFilters();
};
