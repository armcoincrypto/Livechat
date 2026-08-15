<?php
declare(strict_types=1);

namespace iEXPackages\AuthAudit;

use App\Services\DeviceDetectorService;
use iEXPackages\AuthAudit\Context\DeviceIdFactory;
use iEXPackages\AuthAudit\Services\AuditService;
use Illuminate\Support\ServiceProvider;

final class AuthAuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DeviceIdFactory::class, fn () => new DeviceIdFactory());
        $this->app->singleton(Services\AuditContextBuilder::class);

        $this->app->singleton(AuditService::class, function ($app) {
            return new AuditService(
                deviceDetector: $app->make(DeviceDetectorService::class),
                deviceIdFactory: $app->make(DeviceIdFactory::class),
                contextBuilder: $app->make(Services\AuditContextBuilder::class),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \iEXPackages\AuthAudit\Console\ReenrichAuthAuditCommand::class,
                \iEXPackages\AuthAudit\Console\PurgeAuthAuditCommand::class,
            ]);
        }
    }
}
