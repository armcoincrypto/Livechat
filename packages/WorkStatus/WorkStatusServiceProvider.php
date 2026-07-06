<?php

declare(strict_types=1);

namespace iEXPackages\WorkStatus;

use iEXPackages\WorkStatus\Services\WorkStatusService;
use Illuminate\Support\ServiceProvider;

final class WorkStatusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WorkStatusService::class, static fn (): WorkStatusService => new WorkStatusService());
    }
}
