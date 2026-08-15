<?php

use iEXPackages\ExchangerApi\Http\Controllers\AccountController;
use iEXPackages\ExchangerApi\Http\Controllers\AccountPartnersController;
use iEXPackages\ExchangerApi\Http\Controllers\StateController;
use iEXPackages\ExchangerApi\Http\Controllers\XMLRatesController;
use Illuminate\Support\Facades\Route;

// -----------------------------------------------------------------------------
// Public API (без авторизации)
// Итоговые пути: /api/v3/public/*
// -----------------------------------------------------------------------------
Route::prefix('public')
    ->name('public.')
    ->group(function () {
        // XML-выгрузка курсов
        // GET /api/v3/public/rates.xml
        Route::get('rates.xml', [XMLRatesController::class, 'index'])
            ->name('rates.xml');
    });

// -----------------------------------------------------------------------------
// Private API (требуется auth:sanctum)
// Итоговые пути: /api/v3/private/*
// -----------------------------------------------------------------------------
Route::prefix('private')
    ->middleware(['auth:sanctum', 'api_logger'])
    ->name('private.')
    ->group(function () {
        // -------------------------------------------------------------
        // Healthcheck / состояние API
        // GET /api/v3/private/health
        // -------------------------------------------------------------
        Route::get('health', [StateController::class, 'health'])
            ->name('health');

        // -------------------------------------------------------------
        // Учетная запись
        // -------------------------------------------------------------
        Route::prefix('account')
            ->name('account.')
            ->group(function () {

                // Основная информация об аккаунте
                // GET /api/v3/private/account
                Route::get('/', [AccountController::class, 'getAccountInfo'])
                    ->middleware('api.ability:account')
                    ->name('show');

                // -------------------------------------------------
                // Партнерская программа
                // -------------------------------------------------
                Route::prefix('partner')
                    ->middleware('api.ability:partner')
                    ->name('partner.')
                    ->group(function () {
                        // Информация о партнерской программе
                        // GET /api/v3/private/account/partner
                        Route::get('/', [AccountPartnersController::class, 'getPartnerInfo'])
                            ->name('show');

                        // Партнерские обмены
                        // GET /api/v3/private/account/partner/exchanges
                        Route::get('exchanges', [AccountPartnersController::class, 'getPartnerExchanges'])
                            ->name('exchanges.index');

                        // Партнерские выплаты
                        // GET /api/v3/private/account/partner/payouts
                        Route::get('payouts', [AccountPartnersController::class, 'getPartnerWithdrawal'])
                            ->name('payouts.index');
                    });
            });
    });
