<?php

namespace iEXPackages\Payment;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Зарегистрируйте поставщика услуг.
     */
    public function register(): void
    {
        $this->app->singleton('gateways', function (Application $app) {
            return new Payment($app);
        });
    }

    /**
     * Загрузка пакетов.
     */
    public function boot(): void
    {
        //
    }
}
