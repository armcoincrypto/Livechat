<?php

declare(strict_types=1);

namespace iEXPackages\WorkStatus;

use App\Settings\WorkStatusConfig;
use Illuminate\Support\ServiceProvider;
use iEXPackages\WorkStatus\Contracts\WorkStatusResolverInterface;
use iEXPackages\WorkStatus\Resolvers\ManualModeResolver;
use iEXPackages\WorkStatus\Resolvers\ScheduleOverrideResolver;
use iEXPackages\WorkStatus\Resolvers\ScheduleResolver;
use iEXPackages\WorkStatus\Services\WorkScheduleEvaluator;
use iEXPackages\WorkStatus\Services\WorkStatusService;

final class WorkStatusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WorkScheduleEvaluator::class, fn () => new WorkScheduleEvaluator());

        $this->app->singleton(ManualModeResolver::class, fn ($app) => new ManualModeResolver(
            $app->make(WorkStatusConfig::class),
        ));

        $this->app->singleton(ScheduleOverrideResolver::class, fn ($app) => new ScheduleOverrideResolver(
            $app->make(WorkStatusConfig::class),
        ));

        $this->app->singleton(ScheduleResolver::class, fn ($app) => new ScheduleResolver(
            $app->make(WorkStatusConfig::class),
            $app->make(WorkScheduleEvaluator::class),
        ));

        $this->app->singleton(WorkStatusService::class, function ($app) {
            /** @var list<WorkStatusResolverInterface> $resolvers */
            $resolvers = [
                $app->make(ManualModeResolver::class),
                $app->make(ScheduleOverrideResolver::class),
                $app->make(ScheduleResolver::class),
            ];

            return new WorkStatusService(
                $resolvers,
                $app->make(WorkStatusConfig::class),
            );
        });
    }
}
