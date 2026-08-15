<?php

namespace iEXPackages\Analytics;

use iEXPackages\Analytics\Console\RebuildCurrencyAnalyticsDailyCommand;
use iEXPackages\Analytics\Console\RecalculateDailyProfitStats;
use iEXPackages\Analytics\Console\RecalculateDirectionExchangeStatsDaily;
use iEXPackages\Analytics\Console\RecalculateOrderExchangeStatsDaily;
use iEXPackages\Analytics\Console\StatsUsersGlobalDailyCommand;
use iEXPackages\Analytics\Console\TakeReserveTotalSnapshot;
use Illuminate\Support\ServiceProvider;

class AnalyticsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RebuildCurrencyAnalyticsDailyCommand::class,
                RecalculateDailyProfitStats::class,
                RecalculateDirectionExchangeStatsDaily::class,
                RecalculateOrderExchangeStatsDaily::class,
                TakeReserveTotalSnapshot::class,
                StatsUsersGlobalDailyCommand::class
            ]);
        }
    }
}
