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
 * Экспорт снапшота настроек DynamicConfig в JSON-файл.
 *
 * Пример:
 *  php artisan dynamic-config:export
 *  php artisan dynamic-config:export --scope=global
 *  php artisan dynamic-config:export --scope=license --scope-id=5 --path=storage/dynamic-config-license-5.json
 */
final class DynamicConfigExportCommand extends Command
{
    protected $signature = 'dynamic-config:export
    {--scope=global : Scope type}
    {--scope-id= : Scope ID (optional)}
    {--path= : Output file path (optional)}
';

    protected $description = 'Экспорт настроек DynamicConfig для выбранного scope в JSON-файл.';

    public function handle(DynamicConfigManager $manager, ScopeResolverInterface $resolver): int
    {
        $scopeType = strtolower((string) $this->option('scope'));
        $scopeId   = $this->option('scope-id') !== null ? (int) $this->option('scope-id') : null;

        $scope = $this->buildScope($scopeType, $scopeId, $resolver);

        $settings = $manager->all($scope, merged: false);

        $filePath = $this->option('path');

        if (!$filePath) {
            $suffix  = $scopeId === null ? 'null' : (string) $scopeId;
            $filePath = storage_path(sprintf('dynamic-config-%s-%s.json', $scopeType, $suffix));
        }

        try {
            $json = json_encode($settings, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            File::put($filePath, $json);
        } catch (\Throwable $e) {
            $this->error('Не удалось экспортировать настройки в JSON.');
            Log::error('DynamicConfig: ошибка экспорта настроек', [
                'scope_type' => $scopeType,
                'scope_id'   => $scopeId,
                'path'       => $filePath,
                'error'      => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        $this->info(sprintf('Настройки успешно экспортированы в файл: %s', $filePath));

        return self::SUCCESS;
    }

    private function buildScope(string $type, ?int $id): Scope
    {
        return Scope::fromString($type, $id);
    }
}
