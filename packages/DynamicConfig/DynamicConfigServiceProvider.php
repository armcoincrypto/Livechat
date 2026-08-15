<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig;

use iEXPackages\DynamicConfig\Access\ConfigAccessControl;
use iEXPackages\DynamicConfig\Console\DynamicConfigCleanupCommand;
use iEXPackages\DynamicConfig\Console\DynamicConfigDoctorCommand;
use iEXPackages\DynamicConfig\Console\DynamicConfigExportCommand;
use iEXPackages\DynamicConfig\Console\DynamicConfigExportProfilesCommand;
use iEXPackages\DynamicConfig\Console\DynamicConfigImportCommand;
use iEXPackages\DynamicConfig\Console\DynamicConfigLintCommand;
use iEXPackages\DynamicConfig\Console\DynamicConfigListModelsCommand;
use iEXPackages\DynamicConfig\Console\DynamicConfigMakeMigrationCommand;
use iEXPackages\DynamicConfig\Console\DynamicConfigMigrateCommand;
use iEXPackages\DynamicConfig\Console\DynamicConfigSnapshotCreateCommand;
use iEXPackages\DynamicConfig\Console\DynamicConfigSnapshotRestoreCommand;
use iEXPackages\DynamicConfig\Console\MigrateFromJsonCommand;
use iEXPackages\DynamicConfig\Contracts\ScopeResolverInterface;
use iEXPackages\DynamicConfig\Contracts\SettingsStorageInterface;
use iEXPackages\DynamicConfig\Inspection\DynamicConfigInspector;
use iEXPackages\DynamicConfig\Lint\DynamicConfigLinter;
use iEXPackages\DynamicConfig\Migrations\ConfigMigrationRunner;
use iEXPackages\DynamicConfig\Monitoring\DynamicConfigMonitoringService;
use iEXPackages\DynamicConfig\Resolvers\BasicScopeResolver;
use iEXPackages\DynamicConfig\Schema\ConfigSchemaRegistry;
use iEXPackages\DynamicConfig\Services\DynamicConfigSnapshotService;
use iEXPackages\DynamicConfig\Storage\DatabaseSettingsStorage;
use iEXPackages\DynamicConfig\Storage\EloquentSettingsStorage;
use iEXPackages\DynamicConfig\Support\DynamicConfigSystemContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider для модуля DynamicConfig.
 *
 * Регистрирует:
 *  - реестр схем (ConfigSchemaRegistry);
 *  - runner миграций настроек (ConfigMigrationRunner);
 *  - ACL-слой (ConfigAccessControl);
 *  - хранилище настроек (SettingsStorageInterface);
 *  - резолвер scope (ScopeResolverInterface);
 *  - менеджер настроек (DynamicConfigManager);
 *  - инспектор, мониторинг, снапшоты, линтер;
 *  - Artisan-команды для работы с DynamicConfig.
 */
final class DynamicConfigServiceProvider extends ServiceProvider
{
    /**
     * Регистрация сервисов в контейнере.
     */
    public function register(): void
    {
        // Реестр схем
        $this->app->singleton(ConfigSchemaRegistry::class, function (): ConfigSchemaRegistry {
            $basePath       = config('dynamic_config.schema_base_path', storage_path('dynamic-config/schemas'));
            $defaultProfile = 'default';

            return new ConfigSchemaRegistry($basePath, $defaultProfile);
        });

        // Runner миграций настроек DynamicConfig
        $this->app->singleton(ConfigMigrationRunner::class, function (): ConfigMigrationRunner {
            return new ConfigMigrationRunner(DB::connection());
        });

        // ACL (доступ к ключам на основе схемы и spatie/permission)
        $this->app->singleton(ConfigAccessControl::class, function ($app): ConfigAccessControl {
            return new ConfigAccessControl(
                $app->make(ConfigSchemaRegistry::class)
            );
        });

        // Хранилище настроек (eloquent | database)
        $this->app->singleton(SettingsStorageInterface::class, function (): SettingsStorageInterface {
            $driver = config('dynamic_config.driver', 'eloquent');

            return match ($driver) {
                'eloquent' => new EloquentSettingsStorage(),
                default    => new DatabaseSettingsStorage(),
            };
        });

        // Резолвер scope
        $this->app->singleton(ScopeResolverInterface::class, function (): ScopeResolverInterface {
            return new BasicScopeResolver();
        });

        // Менеджер настроек
        $this->app->singleton(DynamicConfigManager::class, function ($app): DynamicConfigManager {
            return new DynamicConfigManager(
                $app->make(SettingsStorageInterface::class),
                $app->make(ScopeResolverInterface::class),
                $app['cache'],
                $app->make(ConfigSchemaRegistry::class),
                $app->make(ConfigAccessControl::class),
            );
        });

        // Инспектор
        $this->app->singleton(DynamicConfigInspector::class, function ($app): DynamicConfigInspector {
            return new DynamicConfigInspector(
                $app->make(DynamicConfigManager::class),
                $app->make(ConfigSchemaRegistry::class),
            );
        });

        // Мониторинг
        $this->app->singleton(DynamicConfigMonitoringService::class, function ($app): DynamicConfigMonitoringService {
            return new DynamicConfigMonitoringService(
                $app->make(DynamicConfigInspector::class),
                $app->make(ConfigSchemaRegistry::class),
            );
        });

        // Снапшоты
        $this->app->singleton(DynamicConfigSnapshotService::class, function ($app): DynamicConfigSnapshotService {
            return new DynamicConfigSnapshotService(
                $app->make(DynamicConfigManager::class),
            );
        });

        // Линтер
        $this->app->singleton(DynamicConfigLinter::class, function ($app): DynamicConfigLinter {
            return new DynamicConfigLinter(
                $app->make(ConfigSchemaRegistry::class),
            );
        });

        $this->app->singleton(DynamicConfigSystemContext::class, fn () => new DynamicConfigSystemContext());
    }

    /**
     * Bootstrap-логика (регистрация команд, публикация и т.п.).
     */
    public function boot(): void
    {
        // Регистрация artisan-команд только в консоли
        if ($this->app->runningInConsole()) {
            $this->commands([
                DynamicConfigSnapshotCreateCommand::class,
                DynamicConfigSnapshotRestoreCommand::class,
                DynamicConfigExportProfilesCommand::class,
                DynamicConfigLintCommand::class,
                DynamicConfigCleanupCommand::class,
                DynamicConfigDoctorCommand::class,
                DynamicConfigMigrateCommand::class,
                MigrateFromJsonCommand::class,
                DynamicConfigExportCommand::class,
                DynamicConfigImportCommand::class,
                DynamicConfigMakeMigrationCommand::class,
                DynamicConfigListModelsCommand::class,
            ]);
        }
    }
}
