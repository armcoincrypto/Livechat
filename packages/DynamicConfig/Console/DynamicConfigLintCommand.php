<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Console;

use iEXPackages\DynamicConfig\Lint\DynamicConfigLinter;
use Illuminate\Console\Command;

/**
 * Линтер схемы и конфигурации DynamicConfig.
 *
 * Примеры:
 *  php artisan dynamic-config:lint
 *  php artisan dynamic-config:lint --with-config
 */
final class DynamicConfigLintCommand extends Command
{
    protected $signature = 'dynamic-config:lint
        {--profile= : Профиль схемы}
        {--with-config : Дополнительно проверить ключи в dynamic_config_settings}
    ';

    protected $description = 'Статический анализ схемы и конфигурации DynamicConfig.';

    public function handle(DynamicConfigLinter $linter): int
    {
        $profile     = $this->option('profile') ?: null;
        $withConfig  = (bool) $this->option('with-config');

        $this->info('Проверка схемы DynamicConfig...');
        $schemaIssues = $linter->lintSchema($profile);

        if ($schemaIssues === []) {
            $this->info('  ✓ Схема: проблем не обнаружено.');
        } else {
            $this->warn(sprintf('  Найдено проблем в схеме: %d', count($schemaIssues)));
            foreach ($schemaIssues as $issue) {
                $this->line(sprintf(
                    '    - [%s] %s: %s',
                    $issue['type'],
                    $issue['key'],
                    $issue['message']
                ));
            }
        }

        if ($withConfig) {
            $this->line('');
            $this->info('Проверка конфигов (dynamic_config_settings) на наличие ключей без схемы...');

            $configIssues = $linter->lintConfigs(true, $profile);

            if ($configIssues === []) {
                $this->info('  ✓ Ключи конфигов: все имеют определения в схеме.');
            } else {
                $this->warn(sprintf('  Найдено проблем в конфигах: %d', count($configIssues)));
                foreach ($configIssues as $issue) {
                    $this->line(sprintf(
                        '    - [%s] %s:%s %s',
                        $issue['type'],
                        $issue['scope_type'],
                        $issue['scope_id'] ?? 'null',
                        $issue['key']
                    ));
                }
            }
        }

        return self::SUCCESS;
    }
}
