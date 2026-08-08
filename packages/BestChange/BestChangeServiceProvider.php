<?php
declare(strict_types=1);

namespace iEXPackages\BestChange;

use App\Settings\BestChangeBlacklistConfig;
use iEXPackages\BestChange\Blacklist\BestChangeBlacklistClient;
use iEXPackages\BestChange\Console\BestchangeCacheCommand;
use iEXPackages\BestChange\Console\BestchangeMarketReportConsole;
use iEXPackages\BestChange\Console\BestchangeUpdateFilesConsole;
use iEXPackages\BestChange\Console\BestchangeUpdateRatesConsole;
use iEXPackages\BestChange\Contracts\BestChangeHttpClientInterface;
use iEXPackages\BestChange\Http\BestChangeHttpClient;
use iEXPackages\BestChange\Services\BestChangeCatalogRepository;
use Illuminate\Support\ServiceProvider;

/**
 * BestChangeServiceProvider
 *
 * Минимальная регистрация:
 * - BestChangeHttpClientInterface -> BestChangeHttpClient
 * - BestChangeCatalogRepository (singleton)
 * - BestChange (singleton) + alias "bestchange"
 *
 * Важно: никаких запросов к БД в provider.
 */
final class BestChangeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BestChangeHttpClientInterface::class, BestChangeHttpClient::class);

        $this->app->singleton(BestChangeCatalogRepository::class);

        $this->app->singleton(BestChange::class, fn () => new BestChange(
            (array) config('courses.bestchange', [])
        ));

        // Клиент Blacklist API
        $this->app->singleton(BestChangeBlacklistClient::class, function ($app): BestChangeBlacklistClient {
            /** @var BestChangeBlacklistConfig $settings */
            $settings = $app->make(BestChangeBlacklistConfig::class);

            return new BestChangeBlacklistClient($settings);
        });

        $this->app->alias(BestChange::class, 'bestchange');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BestchangeUpdateRatesConsole::class,
                BestchangeUpdateFilesConsole::class,
                BestchangeCacheCommand::class,
                BestchangeMarketReportConsole::class
            ]);
        }
    }
}
