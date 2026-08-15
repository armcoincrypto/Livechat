<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use iEXPackages\DynamicConfig\ValueObjects\Scope;

final class DynamicConfigMakeMigrationCommand extends Command
{
    protected $signature = 'dynamic-config:migration
        {name : Имя миграции, например: add_bestchange_interval}
        {--scope=global : Тип scope (global, plugins, language, ...)}
        {--profile= : Явно указать профиль схем/миграций (по умолчанию = scope_type)}
    ';

    protected $description = 'Создать файл миграции для DynamicConfig';

    public function handle(): int
    {
        $name      = Str::snake($this->argument('name'));
        $scopeType = strtolower((string) $this->option('scope'));
        $profile   = $this->option('profile') ?: $scopeType;

        $basePath  = config('dynamic_config.migrations.base_path', base_path('dynamic-config/migrations'));
        $profiles  = config('dynamic_config.migrations.profiles', []);
        $subdir    = $profiles[$profile] ?? ($profiles['default'] ?? 'default');

        $dir = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $subdir;

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $timestamp = date('Y_m_d_His');
        $filename  = "{$timestamp}_{$name}.php";
        $path      = $dir . DIRECTORY_SEPARATOR . $filename;

        File::put($path, $this->stubContent($name));

        $this->info("Миграция DynamicConfig создана: {$path}");

        return self::SUCCESS;
    }

    private function stubContent(string $name): string
    {
        return <<<PHP
<?php

use iEXPackages\DynamicConfig\Facades\DynamicConfig;
use iEXPackages\DynamicConfig\ValueObjects\Scope;

/**
 * Миграция DynamicConfig: {$name}
 *
 * Пример:
 *  php artisan dynamic-config:migrate --scope=plugins
 */
return new class
{
    public function up(Scope \$scope): void
    {
        // Пример: добавить новый ключ с дефолтным значением
        // DynamicConfig::setWithMode('bestchange.some_flag', true, 'force', \$scope);
    }

    public function down(Scope \$scope): void
    {
        // Пример: откатить изменение
        // DynamicConfig::delete('bestchange.some_flag', \$scope);
    }
};
PHP;
    }
}
