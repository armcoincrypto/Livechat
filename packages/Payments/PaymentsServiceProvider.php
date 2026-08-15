<?php

declare(strict_types=1);

namespace iEXPackages\Payments;

use App\Settings\GatewayConfig;
use iEXPackages\Payments\Console\GenerateGatewayInputs;
use iEXPackages\Payments\Console\MakeGatewayScaffold;
use iEXPackages\Payments\Core\Contracts\GatewayHttpClientInterface;
use iEXPackages\Payments\Core\Sandbox\Contracts\SandboxManagerInterface;
use iEXPackages\Payments\Core\Sandbox\ReplayStore;
use iEXPackages\Payments\Core\Sandbox\SandboxManager;
use iEXPackages\Payments\Core\Security\Contracts\SecretAccessManagerInterface;
use iEXPackages\Payments\Core\Security\SecretAccessManager;
use iEXPackages\Payments\Core\Services\GatewayHttpClient;
use iEXPackages\Payments\Core\Services\GatewaySoapClient;
use iEXPackages\Payments\Core\Services\SandboxHttpClientDecorator;
use iEXPackages\Payments\Logging\GatewayLogger;
use iEXPackages\Payments\Logging\SensitiveDataMasker;
use Illuminate\Support\ServiceProvider;
use iEXPackages\Payments\Core\Engine\GatewayManager;

final class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /**
         * ------------------------------------------------------------
         * Logging & Security
         * ------------------------------------------------------------
         */
        $this->app->singleton(SensitiveDataMasker::class);
        $this->app->singleton(GatewayLogger::class);

        $this->app->singleton(
            SecretAccessManagerInterface::class,
            SecretAccessManager::class
        );

        /**
         * ------------------------------------------------------------
         * Sandbox / Replay infrastructure
         * ------------------------------------------------------------
         */
        $this->app->singleton(ReplayStore::class);

        $this->app->singleton(
            SandboxManagerInterface::class,
            fn ($app) => new SandboxManager($app->make(ReplayStore::class))
        );


        $this->app->singleton(GatewaySoapClient::class, function ($app) {
            return new GatewaySoapClient(
                logger: $app->make(GatewayLogger::class),
                sandbox: $app->make(SandboxManagerInterface::class),
            );
        });

        /**
         * ------------------------------------------------------------
         * Gateway HTTP Client (BASE + optional SANDBOX)
         * ------------------------------------------------------------
         *
         * Всегда создаём базовый GatewayHttpClient.
         * Если sandbox включён — оборачиваем его в SandboxHttpClientDecorator.
         */
        $this->app->singleton(
            GatewayHttpClientInterface::class,
            function ($app) {

                $baseClient = new GatewayHttpClient(
                    logger: $app->make(GatewayLogger::class),
                    config: $app->make(GatewayConfig::class),
                );

                if (!config('payments.sandbox.enabled', false)) {
                    return $baseClient;
                }

                return new SandboxHttpClientDecorator(
                    inner: $baseClient,
                    sandbox: $app->make(SandboxManagerInterface::class),
                    logger: $app->make(GatewayLogger::class),
                );
            }
        );

        /**
         * ------------------------------------------------------------
         * GatewayManager (обнаружение шлюзов)
         * ------------------------------------------------------------
         */
        $this->app->singleton(GatewayManager::class, function () {
            $cfg  = config('payments', []);
            $map  = $cfg['gateways'] ?? [];
            $scan = $cfg['scan'] ?? [];

            $manager = new GatewayManager($map, $scan);
            $manager->discoverGateways();

            return $manager;
        });

        /**
         * ------------------------------------------------------------
         * PaymentsManager — публичная точка входа
         * ------------------------------------------------------------
         */
        $this->app->singleton('payments', function ($app) {
            return new PaymentsManager(
                $app->make(GatewayManager::class)
            );
        });

        // Удобный alias
        $this->app->alias('payments', PaymentsManager::class);
    }

    public function boot(): void
    {
        $this->commands([
            GenerateGatewayInputs::class,
            MakeGatewayScaffold::class,
            \iEXPackages\Payments\Console\ValidateGatewaysConfig::class,
            \iEXPackages\Payments\Console\GatewayHealthCheckCommand::class,
        ]);
    }
}
