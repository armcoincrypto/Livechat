<?php
declare(strict_types=1);

namespace iEXPackages\Proxy;

use iEXPackages\Proxy\Console\ProxyPruneHealthLogsCommand;
use Illuminate\Support\ServiceProvider;
use iEXPackages\Proxy\Services\ProxyHealthService;
use iEXPackages\Proxy\Services\ProxyHttpApplier;
use iEXPackages\Proxy\Services\ProxyRepository;
use iEXPackages\Proxy\Services\ProxyUrlBuilder;

/**
 * ProxyServiceProvider
 *
 * Регистрирует зависимости пакета.
 *
 * Важно:
 * - НИКАКИХ запросов к БД в provider.
 * - Только биндинги контейнера.
 */
final class ProxyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProxyRepository::class, static fn () => new ProxyRepository());

        $this->app->singleton(ProxyUrlBuilder::class, static fn () => new ProxyUrlBuilder());
        $this->app->singleton(ProxyHttpApplier::class, static fn () => new ProxyHttpApplier());

        $this->app->singleton(ProxyHealthService::class, static fn () => new ProxyHealthService(
            failThreshold: (int) (config('proxy.fail_threshold', 3))
        ));

        $this->app->singleton(ProxyManager::class, function ($app) {
            return new ProxyManager(
                repository: $app->make(ProxyRepository::class),
                urlBuilder: $app->make(ProxyUrlBuilder::class),
                httpApplier: $app->make(ProxyHttpApplier::class),
                health: $app->make(ProxyHealthService::class),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProxyPruneHealthLogsCommand::class,
            ]);
        }
    }
}
