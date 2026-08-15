<?php

namespace iEXPackages\Order;

use iEXPackages\Order\Order;
use iEXPackages\Order\Invoices\RequisiteManager;
use iEXPackages\Order\Validation\Effects\EffectsApplier;
use iEXPackages\Order\Validation\Effects\Handlers\AmlSnapshotEffectHandler;
use iEXPackages\Order\Validation\Effects\Handlers\CardInfoSnapshotEffectHandler;
use iEXPackages\Order\Validation\Effects\Handlers\FreezeScamEffectHandler;
use iEXPackages\Order\Validation\ValidationRegistry;
use Illuminate\Support\ServiceProvider;

class OrderServiceProvider extends ServiceProvider
{
    public function boot()
    {
        //
    }

    public function register()
    {
        /**
         * Register the main Order service
         *
         * @return Order
         */
        $this->app->singleton('order', function () {
            return new Order();
        });

        $this->app->singleton(ValidationRegistry::class);

        $this->app->singleton(EffectsApplier::class, function ($app) {
            return new EffectsApplier([
                $app->make(FreezeScamEffectHandler::class),
                $app->make(AmlSnapshotEffectHandler::class),
                $app->make(CardInfoSnapshotEffectHandler::class),
            ]);
        });

        /**
         * Register the RequisiteManager service
         *
         * @return RequisiteManager
         */
        $this->app->singleton('order.invoice', function () {
            return new RequisiteManager();
        });
    }
}
