<?php

declare(strict_types=1);

use App\Providers\Administrator\AdminNotifyServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AttributionServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\RatesOwnerControlServiceProvider;
use App\Providers\TelescopeServiceProvider;
use App\Providers\TelegramHttpTimeoutServiceProvider;
use iEXPackages\DynamicConfig\DynamicConfigServiceProvider;
use iEXPackages\WorkStatus\WorkStatusServiceProvider;

/*
|--------------------------------------------------------------------------
| Application Service Providers
|--------------------------------------------------------------------------
| Здесь регистрируются сервис-провайдеры приложения.
| Список минимален для production и расширяется в dev/test окружениях.
|--------------------------------------------------------------------------
*/

$administratorProviders = [
    AdminNotifyServiceProvider::class
];


$providers = [
    /*
     * Core application providers
     */
    DynamicConfigServiceProvider::class,
    AppServiceProvider::class,
    RatesOwnerControlServiceProvider::class,
    AttributionServiceProvider::class,
    AuthServiceProvider::class,
    HorizonServiceProvider::class,
    WorkStatusServiceProvider::class,
    TelegramHttpTimeoutServiceProvider::class,

    ...$administratorProviders
];

/*
 * Development & testing providers
 */
if (app()->environment(['local', 'testing'])) {
    $providers[] = TelescopeServiceProvider::class;
}

return $providers;
