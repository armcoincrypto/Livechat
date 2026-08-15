<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Console;

use iEXPackages\DynamicConfig\Contracts\ScopeResolverInterface;
use iEXPackages\DynamicConfig\DynamicConfigManager;
use iEXPackages\DynamicConfig\ValueObjects\Scope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Импорт снапшота настроек DynamicConfig из JSON-файла в выбранный scope.
 *
 * Пример:
 *  php artisan dynamic-config:import storage/dynamic-config-global-null.json
 *  php artisan dynamic-config:import storage/custom.json --scope=license --scope-id=5
 */
final class DynamicConfigImportCommand extends Command
{
    protected $signature = 'dynamic-config:import
                            {path? : Путь к JSON-файлу со снапшотом настроек}
                            {--scope=global : Тип scope (global, license, exchange, user)}
                            {--scope-id= : ID scope (для global — не указывать)}';

    protected $description = 'Импорт настроек DynamicConfig из JSON-файла в выбранный scope.';

    public function handle(DynamicConfigManager $manager, ScopeResolverInterface $resolver): int
    {
        $path = $this->argument('path');
        if (!$path) {
            $path = storage_path('app/iex-config.json');
            $this->info('Аргумент "path" не указан, используется путь по умолчанию: ' . $path);
        }
        $path = (string) $path;

        if (!File::exists($path)) {
            $this->error(sprintf('Файл не найден: %s', $path));

            return self::FAILURE;
        }

        try {
            $raw  = File::get($path);
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->error('Ошибка чтения или парсинга JSON-файла настроек.');
            Log::error('DynamicConfig: ошибка импорта JSON', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        if (!is_array($data)) {
            $this->error('Некорректный формат JSON: ожидался объект с настройками.');

            return self::FAILURE;
        }

        $scopeType = strtolower((string) $this->option('scope'));
        $scopeId   = $this->option('scope-id') !== null ? (int) $this->option('scope-id') : null;

        $scope = $this->buildScope($scopeType, $scopeId, $resolver);

        try {
            $manager->update($data, $scope);
        } catch (\Throwable $e) {
            $this->error('Ошибка сохранения настроек в DynamicConfig.');
            Log::error('DynamicConfig: ошибка импорта настроек', [
                'scope_type' => $scopeType,
                'scope_id'   => $scopeId,
                'path'       => $path,
                'error'      => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        $this->info('Настройки успешно импортированы в DynamicConfig.');

        return self::SUCCESS;
    }

    private function buildScope(string $type, ?int $id): Scope
    {
        return Scope::fromString($type, $id);
    }
}
