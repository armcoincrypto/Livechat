<?php

use iEXPackages\Analytics\Http\Controllers\Exchanges\CurrencyAnalyticsTotalsController;
use iEXPackages\Analytics\Http\Controllers\Exchanges\DirectionsAnalyticsController;
use iEXPackages\Analytics\Http\Controllers\Exchanges\OrderExchangeTotalsController;
use iEXPackages\Analytics\Http\Controllers\Exchanges\OrdersAnalyticsController;
use iEXPackages\Analytics\Http\Controllers\Exchanges\ProfitAnalyticsController;
use iEXPackages\Analytics\Http\Controllers\Exchanges\ReservesSummaryController;
use iEXPackages\Analytics\Http\Controllers\Partners\ReferralAnalyticsController;
use iEXPackages\Analytics\Http\Controllers\Partners\ReferralExchangesController;
use iEXPackages\Analytics\Http\Controllers\Partners\ReferralReferralsAnalyticsController;
use iEXPackages\Analytics\Http\Controllers\Partners\ReferralRelationsController;
use iEXPackages\Analytics\Http\Controllers\Reserves\ReserveAnalyticsController;
use iEXPackages\Analytics\Http\Controllers\Users\UsersGlobalAnalyticsController;
use Illuminate\Support\Facades\Route;


Route::prefix('users')
    ->name('users.')
    ->middleware(['permission:admin_analytics_users'])
    ->group(function () {
        // Глобальная аналитика пользователей
        Route::get('global', [UsersGlobalAnalyticsController::class, 'index'])
            ->name('global.index');
    });


// Партнёры / рефералы
Route::prefix('partners')
    ->name('partners.')
    ->middleware(['permission:admin_analytics_partners'])
    ->group(function () {
        // Список партнёрских обменов
        Route::get('/exchanges', [ReferralExchangesController::class, 'index'])
            ->name('exchanges.index');

        // Сводная аналитика партнёрских обменов
        Route::get('/exchanges/summary', [ReferralExchangesController::class, 'summary'])
            ->name('exchanges.summary');

        // Общая реферальная аналитика
        Route::get('/overview', [ReferralAnalyticsController::class, 'index'])
            ->name('overview.index');

        // Сводная аналитика рефералов
        Route::get('/relations-summary', [ReferralReferralsAnalyticsController::class, 'summary'])
            ->name('relations.summary');

        // Список реферальных отношений с фильтрами
        Route::get('/relations', [ReferralRelationsController::class, 'index'])
            ->name('relations.index');
    });

// Направления
Route::prefix('directions')
    ->middleware(['permission:admin_analytics_exchanges'])
    ->name('directions.')
    ->group(function () {
        Route::get('/', [DirectionsAnalyticsController::class, 'index'])
            ->name('index');

        Route::get('/all', [DirectionsAnalyticsController::class, 'all'])
            ->name('all');

        Route::get('/no-demand', [DirectionsAnalyticsController::class, 'noDemand'])
            ->name('noDemand');

        Route::get('/top', [DirectionsAnalyticsController::class, 'top'])
            ->name('top');
    });

/*
|--------------------------------------------------------------------------
| Аналитика обменов
|--------------------------------------------------------------------------
*/
Route::prefix('orders')
    ->middleware(['permission:admin_analytics_exchanges'])
    ->name('orders.')->group(function () {
    Route::get('/summary', [OrdersAnalyticsController::class, 'getOrderStat'])
        ->name('summary');

    Route::get('/dashboard', [OrdersAnalyticsController::class, 'dashboard'])
        ->name('dashboard');
});

// Аналитика сумм обменов (order_exchange_totals)
Route::prefix('exchange-totals')
    ->middleware(['permission:admin_analytics_exchanges'])
    ->name('exchange_totals.')->group(function () {
    Route::get('/summary', [OrderExchangeTotalsController::class, 'summary'])
        ->name('summary');

    Route::get('/daily', [OrderExchangeTotalsController::class, 'daily'])
        ->name('daily');

    Route::get('/managers', [OrderExchangeTotalsController::class, 'managers'])
        ->name('managers');

    Route::get('/daily-with-managers', [OrderExchangeTotalsController::class, 'dailyWithManagers'])
        ->name('daily_with_managers');

    Route::get('/top-low-days', [OrderExchangeTotalsController::class, 'topAndLowDays'])
        ->name('top_low_days');
});

// Валюты
Route::prefix('currencies')
    ->middleware(['permission:admin_analytics_exchanges'])
    ->name('currencies.')->group(function () {
    Route::get('/totals', [CurrencyAnalyticsTotalsController::class, 'index'])
        ->name('totals');

    Route::get('/period', [CurrencyAnalyticsTotalsController::class, 'period'])
        ->name('period');
});

Route::get('/reserves-summary', [ReservesSummaryController::class, 'index'])->middleware(['permission:admin_analytics_exchanges']);

/*
|--------------------------------------------------------------------------
| Аналитика прибыли
|--------------------------------------------------------------------------
*/
Route::prefix('profit')
    ->middleware(['permission:admin_analytics_exchanges'])
    ->name('profit.')->group(function () {
    Route::get('/orders', [ProfitAnalyticsController::class, 'orders'])
        ->name('orders');

    Route::get('/periods', [ProfitAnalyticsController::class, 'periods'])
        ->name('periods');

    Route::get('/daily-chart', [ProfitAnalyticsController::class, 'dailyChart'])
        ->name('dailyChart');

    Route::get('/order/{task}', [ProfitAnalyticsController::class, 'orderDetails'])
        ->name('orderDetails');

    Route::get('/overview', [ProfitAnalyticsController::class, 'profitOverview'])
        ->name('overview');

    Route::get('/kpis', [ProfitAnalyticsController::class, 'profitKpis'])
        ->name('kpis');

    Route::get('/status-overview', [ProfitAnalyticsController::class, 'statusOverview'])
        ->name('statusOverview');

    Route::get('/managers', [ProfitAnalyticsController::class, 'managerProfit'])
        ->name('managers');

    Route::get('/heatmap', [ProfitAnalyticsController::class, 'profitHeatmap'])
        ->name('heatmap');
});


/*
|--------------------------------------------------------------------------
| Аналитика резервов (движение / ledger)
|--------------------------------------------------------------------------
*/
Route::prefix('reserves-ledger')
    ->middleware(['permission:admin_analytics_exchanges'])
    ->name('reserves_ledger.')
    ->group(function () {
        Route::get('/kpis', [ReserveAnalyticsController::class, 'kpis'])->name('kpis');
        Route::get('/periods', [ReserveAnalyticsController::class, 'periods'])->name('periods');
        Route::get('/ledger', [ReserveAnalyticsController::class, 'ledger'])->name('ledger');
    });
