<?php

use iEXPackages\DynamicConfig\Facades\DynamicConfig;
use iEXPackages\DynamicConfig\ValueObjects\Scope;

/**
 * Миграция DynamicConfig: referral_fallback_code_currencies
 *
 * Пример:
 *  php artisan dynamic-config:migrate --scope=plugins
 */
return new class
{
    public function up(Scope $scope): void
    {
        // Пример: добавить новый ключ с дефолтным значением
        DynamicConfig::setWithMode('global.referral_fallback_code_currencies', '', 'force', $scope);
    }

    public function down(Scope $scope): void
    {
        // Пример: откатить изменение
        // DynamicConfig::delete('bestchange.some_flag', $scope);
    }
};
