<?php

namespace iEXPackages\ExchangerApi;

use iEXPackages\ExchangerApi\Models\PersonalAccessToken;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class APIServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }

    /**
     * Register routes for API
     */
    private function registerRoutes(): void
    {
        Route::group([
            'prefix' => 'api',
        ], function () {
            Route::prefix('v3')->group(function () {
                $this->loadRoutesFrom(__DIR__.'/routes/api.php');
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
