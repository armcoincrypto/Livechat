<?php

namespace iEXPackages\Transaction;

use Illuminate\Support\ServiceProvider;

class TransactionServiceProvider extends ServiceProvider
{
    public function boot()
    {
        //
    }

    public function register()
    {
        $this->app->singleton('transaction', function () {
            return new Transaction();
        });
    }
}
