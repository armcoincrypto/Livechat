<?php

namespace iEXPackages\ExchangerClient;

use iEXPackages\ExchangerClient\Services\StartService\StartService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ClientServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerRoutes();

        $this->app->singleton('exchanger-client.start', function (Application $app) {
            return new StartService($app);
        });
    }

    /**
     * Register routes for API
     */
    private function registerRoutes(): void
    {
        Route::group([
            'prefix' => 'client-api',
            'middleware' => ['web', 'throttle:api-frontend', 'frontend', 'client-web']
        ], function () {
            Route::prefix('v1')->group(function () {
                $this->loadRoutesFrom(__DIR__.'/routes/client-api.php');
            });
        });

        // Если в API есть ошибки
        Route::fallback(function () {
            return response()->json([
                'code' => -1,
                'message' => 'Not Found',
            ], 404);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
