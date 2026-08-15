<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Migrations;

use iEXPackages\DynamicConfig\ValueObjects\Scope;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Отвечает за выполнение миграций настроек DynamicConfig.
 *
 * Ищет PHP-файлы миграций в заданной директории, проверяет,
 * какие из них уже применены для конкретного scope, и выполняет только новые.
 *
 * Формат миграции:
 *
 *  return new class {
 *      public function up(\iEXPackages\DynamicConfig\ValueObjects\Scope $scope): void {
 *          // любая логика: чтение/запись настроек через DynamicConfig
 *      }
 *
 *      public function down(\iEXPackages\DynamicConfig\ValueObjects\Scope $scope): void {
 *          // (опционально) откат
 *      }
 *  };
 */
final class ConfigMigrationRunner
{
    private ConnectionInterface $connection;

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Запускает все новые миграции для указанного scope.
     *
     * @param string|null $customPath Путь к миграциям относительно base_path(), если нужно переопределить
     * @param Scope|null  $scope      Scope для применения миграций (по умолчанию global)
     *
     * @return int Количество выполненных миграций
     */
    public function run(?string $customPath = null, ?Scope $scope = null): int
    {
        $scope ??= Scope::global();

        // БАЗОВЫЙ ПУТЬ
        $basePath = config('dynamic_config.migrations.base_path', storage_path('dynamic-config/migrations'));

        // ОПРЕДЕЛЯЕМ ПРОФИЛЬ (по умолчанию — scope_type)
        if ($customPath !== null) {
            // кастомный путь — полный или относительный от storage_path
            $fullPath = storage_path($customPath);
        } else {
            $profile = $scope->type ?? 'default';

            $profilesMap = config('dynamic_config.migrations.profiles', []);
            $subdir      = $profilesMap[$profile] ?? ($profilesMap['default'] ?? 'default');

            $fullPath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $subdir;
        }

        if (!File::isDirectory($fullPath)) {
            File::makeDirectory($fullPath, 0755, true, true);

            Log::info('DynamicConfig: каталог с миграциями был автоматически создан', [
                'path'      => $fullPath,
                'scope'     => $scope->type,
                'scope_id'  => $scope->id,
            ]);

            return 0;
        }

        // дальше — ВЕСЬ твой текущий код по сбору файлов и их выполнению
        $files = collect(File::files($fullPath))
            ->filter(fn ($file) => \Illuminate\Support\Str::endsWith($file->getFilename(), '.php'))
            ->sortBy(fn ($file) => $file->getFilename())
            ->values()
            ->all();

        if ($files === []) {
            return 0;
        }

        $batch   = $this->getNextBatchNumber();
        $applied = 0;

        foreach ($files as $file) {
            $migrationName = Str::replaceLast('.php', '', $file->getFilename());

            if ($this->alreadyRan($migrationName, $scope)) {
                continue;
            }

            $migrationInstance = $this->resolveMigrationInstance($file->getPathname());

            if ($migrationInstance === null) {
                continue;
            }

            try {
                $this->connection->transaction(function () use ($migrationInstance, $scope, $migrationName, $batch): void {
                    $migrationInstance->up($scope);

                    $this->logMigrationAsRan($migrationName, $scope, $batch);
                });

                $applied++;
                Log::info('DynamicConfig: миграция настроек применена', [
                    'migration'  => $migrationName,
                    'scope_type' => $scope->type,
                    'scope_id'   => $scope->id,
                ]);

            } catch (\Throwable $e) {
                Log::error('DynamicConfig: ошибка при выполнении миграции настроек', [
                    'migration'  => $migrationName,
                    'scope_type' => $scope->type,
                    'scope_id'   => $scope->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $applied;
    }

    // ---------------------------------------------------------------------
    // Внутренние методы
    // ---------------------------------------------------------------------

    /**
     * Проверяет, была ли уже выполнена миграция для данного scope.
     */
    private function alreadyRan(string $migration, Scope $scope): bool
    {
        return $this->connection
            ->table('dynamic_config_migrations')
            ->where('migration', $migration)
            ->where('scope_type', $scope->type)
            ->where('scope_id', $scope->id)
            ->exists();
    }

    /**
     * Логирует факт выполнения миграции.
     */
    private function logMigrationAsRan(string $migration, Scope $scope, int $batch): void
    {
        $this->connection
            ->table('dynamic_config_migrations')
            ->insert([
                'migration'  => $migration,
                'scope_type' => $scope->type,
                'scope_id'   => $scope->id,
                'batch'      => $batch,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Возвращает номер следующего batch (как в стандартных миграциях Laravel).
     */
    private function getNextBatchNumber(): int
    {
        $max = $this->connection
            ->table('dynamic_config_migrations')
            ->max('batch');

        return $max ? ((int) $max + 1) : 1;
    }

    /**
     * Загружает файл миграции и возвращает его инстанс.
     *
     * Ожидается, что файл возвращает объект с методом up(Scope $scope).
     */
    private function resolveMigrationInstance(string $path): ?object
    {
        try {
            /** @var object|null $instance */
            $instance = require $path;

            if (!is_object($instance) || !method_exists($instance, 'up')) {
                Log::error('DynamicConfig: файл миграции не вернул корректный объект', [
                    'path' => $path,
                ]);

                return null;
            }

            return $instance;
        } catch (\Throwable $e) {
            Log::error('DynamicConfig: ошибка загрузки файла миграции', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
