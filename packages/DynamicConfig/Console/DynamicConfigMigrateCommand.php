<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Console;

use iEXPackages\DynamicConfig\Migrations\ConfigMigrationRunner;
use iEXPackages\DynamicConfig\ValueObjects\Scope;
use Illuminate\Console\Command;

/**
 * Команда для выполнения миграций настроек DynamicConfig.
 *
 * Примеры:
 *  php artisan dynamic-config:migrate
 *  php artisan dynamic-config:migrate --path=dynamic-config/migrations
 *  php artisan dynamic-config:migrate --scope=license --scope-id=123
 */
final class DynamicConfigMigrateCommand extends Command
{
    /**
     * Имя и сигнатура команды.
     */
    protected $signature = 'dynamic-config:migrate
        {--path= : Путь к миграциям относительно base_path(), по умолчанию из config("dynamic_config.migrations_path")}
        {--scope=global : Тип scope (global, license, exchange, user)}
        {--scope-id= : ID scope (для global не указывать)}';

    /**
     * Описание команды.
     */
    protected $description = 'Выполнить миграции настроек DynamicConfig.';

    public function handle(ConfigMigrationRunner $runner): int
    {
        $path      = $this->option('path') ?: null;
        $scopeType = strtolower((string) $this->option('scope'));
        $scopeId   = $this->option('scope-id') !== null
            ? (int) $this->option('scope-id')
            : null;

        $scope = $this->buildScope($scopeType, $scopeId);

        $this->info(sprintf(
            'Запуск миграций DynamicConfig для scope "%s:%s"...',
            $scope->type,
            $scope->id === null ? 'null' : (string) $scope->id
        ));

        $count = $runner->run($path, $scope);

        if ($count === 0) {
            $this->info('Новых миграций настроек не найдено.');
        } else {
            $this->info(sprintf('Выполнено миграций: %d', $count));
        }

        return self::SUCCESS;
    }

    /**
     * Строит Scope на основании параметров командной строки.
     */
    private function buildScope(string $scopeType, ?int $scopeId): Scope
    {
        return Scope::fromString($scopeType, $scopeId);
    }
}
