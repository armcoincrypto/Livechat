<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Console;

use iEXPackages\DynamicConfig\DynamicConfigManager;
use iEXPackages\DynamicConfig\Schema\ConfigSchemaRegistry;
use iEXPackages\DynamicConfig\ValueObjects\Scope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class DynamicConfigExportProfilesCommand extends Command
{
    protected $signature = 'dynamic-config:export-profiles
        {--scope=global : Тип scope}
        {--scope-id= : ID scope}
        {--profile= : Профиль схемы (schemaProfile)}
        {--groups= : Группы через запятую (security,currency,ui)}
        {--keys= : Паттерны ключей через запятую (debug.*,env_*,currency.*)}
        {--path= : Путь к файлу JSON (по умолчанию storage/dynamic-config-export-{scope}-{id}.json)}
    ';

    protected $description = 'Экспорт части настроек DynamicConfig по группам и паттернам ключей.';

    public function handle(
        DynamicConfigManager $manager,
        ConfigSchemaRegistry $schemaRegistry
    ): int {
        $scopeType = (string) $this->option('scope');
        $scopeId   = $this->option('scope-id') !== null ? (int) $this->option('scope-id') : null;
        $profile   = $this->option('profile') ?: null;

        $groupsOpt = $this->option('groups');
        $keysOpt   = $this->option('keys');

        $groups = $groupsOpt ? array_filter(array_map('trim', explode(',', $groupsOpt))) : [];
        $keyPatterns = $keysOpt ? array_filter(array_map('trim', explode(',', $keysOpt))) : [];

        $scope = Scope::fromString($scopeType, $scopeId);

        $settings = $manager->all($scope, merged: false);
        $flat     = \Illuminate\Support\Arr::dot($settings);

        $result = [];

        foreach ($flat as $key => $value) {
            $def = $schemaRegistry->findDefinition($key, $profile);

            // Фильтр по группам
            if ($groups !== []) {
                $group = $def['group'] ?? 'other';
                if (!in_array($group, $groups, true)) {
                    continue;
                }
            }

            // Фильтр по паттернам ключей
            if ($keyPatterns !== []) {
                $ok = false;
                foreach ($keyPatterns as $pattern) {
                    if (Str::is($pattern, $key)) {
                        $ok = true;
                        break;
                    }
                }
                if (!$ok) {
                    continue;
                }
            }

            // Если фильтров нет — берём всё
            $result[$key] = $value;
        }

        if ($result === []) {
            $this->warn('Под заданные фильтры не попало ни одного ключа.');
            return self::SUCCESS;
        }

        $path = $this->option('path');
        if (!$path) {
            $suffix = $scope->id === null ? 'null' : (string) $scope->id;
            $path   = storage_path(sprintf('dynamic-config-export-%s-%s.json', $scope->type, $suffix));
        }

        $dir = dirname($path);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true, true);
        }

        $json = json_encode(
            $result,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        File::put($path, $json);

        $this->info(sprintf('Экспортировано ключей: %d', count($result)));
        $this->info(sprintf('Файл: %s', $path));

        return self::SUCCESS;
    }
}
