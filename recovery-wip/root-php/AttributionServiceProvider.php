<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Analytics\AttributionIngestController;
use App\Services\Analytics\AttributionFeatures;
use App\Services\Analytics\AttributionOrderLinker;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Batch 11 attribution wiring (feature-flagged).
 */
final class AttributionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AttributionOrderLinker::class);
    }

    public function boot(): void
    {
        if (! AttributionFeatures::anyEnabled() && ! $this->app->runningInConsole()) {
            // Still register route so dark-launch can flip env without redeploying routes file,
            // but controller short-circuits when disabled.
        }

        Route::middleware(['api', 'throttle:60,1'])
            ->prefix('apis/client-api/v1')
            ->group(function () {
                Route::post('attribution/session', [AttributionIngestController::class, 'store']);
            });

        // Fail-open post-create hook when Eloquent Task model is available.
        if (class_exists(\App\Models\Task::class) && AttributionFeatures::orderLinkEnabled()) {
            \App\Models\Task::created(function ($task) {
                try {
                    $options = [];
                    if (request()) {
                        $options = (array) request()->all();
                    }
                    $linker = app(AttributionOrderLinker::class);
                    $linker->attachFailOpen($task, $linker->sessionIdFromOptions($options));
                } catch (\Throwable) {
                    // never break create
                }
            });
        }
    }
}
