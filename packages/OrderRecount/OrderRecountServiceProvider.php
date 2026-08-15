<?php

declare(strict_types=1);

namespace iEXPackages\OrderRecount;

use iEXPackages\OrderRecount\Console\OrderRecountAggregateDailyCommand;
use iEXPackages\OrderRecount\Console\OrderRecountExplainCommand;
use iEXPackages\OrderRecount\Console\OrderRecountRunCommand;
use iEXPackages\OrderRecount\Services\OrderRecountEngine;
use iEXPackages\OrderRecount\Support\PolicyRepository;
use iEXPackages\OrderRecount\Support\RateProvider;
use iEXPackages\OrderRecount\Support\RecountExecutor;
use Illuminate\Support\ServiceProvider;

/**
 * OrderRecountServiceProvider
 *
 * Регистрирует модуль пересчёта заявок.
 *
 * Принципы:
 * - Минимум магии, максимум читаемости.
 * - Все зависимости модуля объявлены явно.
 * - Facade OrderRecount использует accessor `order-recount`.
 */
final class OrderRecountServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Внутренние зависимости (singleton, т.к. статлесс/дешёвые)
        $this->app->singleton(PolicyRepository::class, fn () => new PolicyRepository());
        $this->app->singleton(RateProvider::class, fn () => new RateProvider());
        $this->app->singleton(RecountExecutor::class, fn () => new RecountExecutor());

        // Engine
        $this->app->singleton(OrderRecountEngine::class, function ($app) {
            return new OrderRecountEngine(
                policies: $app->make(PolicyRepository::class),
                rates: $app->make(RateProvider::class),
                executor: $app->make(RecountExecutor::class),
            );
        });

        // Facade accessor: order-recount
        $this->app->singleton('order-recount', function ($app) {
            return new OrderRecountManager(
                engine: $app->make(OrderRecountEngine::class)
            );
        });
    }

    public function boot(): void
    {
        // Команды
        if ($this->app->runningInConsole()) {
            $this->commands([
                OrderRecountRunCommand::class,
                OrderRecountExplainCommand::class,
                OrderRecountAggregateDailyCommand::class
            ]);
        }
    }
}
