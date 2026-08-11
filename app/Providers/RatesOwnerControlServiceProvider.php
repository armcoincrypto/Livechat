<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\DirectionExchange;
use App\Observers\CommercialAdjustmentOwnershipObserver;
use Illuminate\Support\ServiceProvider;

/**
 * Registers owner-controlled commercial-adjustment monitoring.
 */
final class RatesOwnerControlServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        if (class_exists(DirectionExchange::class)) {
            DirectionExchange::observe(CommercialAdjustmentOwnershipObserver::class);
        }
    }
}
