<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Console;

use iEXPackages\DynamicConfig\Inspection\DynamicConfigInspector;
use iEXPackages\DynamicConfig\ValueObjects\Scope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Auto-cleanup & auto-fix для DynamicConfig.
 *
 * Примеры:
 *  php artisan dynamic-config:cleanup
 *  php artisan dynamic-config:cleanup --scope=license --scope-id=123
 *  php artisan dynamic-config:cleanup --aggressive
 */
final class DynamicConfigCleanupCommand extends Command
{
    protected $signature = 'dynamic-config:cleanup
        {--scope= : Тип scope (global, license, exchange, user, пусто = все)}
        {--scope-id= : ID scope (если нужно ограничить одним scope)}
        {--profile= : Профиль схемы (schemaProfile)}
        {--aggressive : Агрессивный режим (удалять unknown и deprecated)}
    ';

    protected $description = 'Авто-очистка и авто-фикс настроек DynamicConfig по схеме.';

    public function handle(DynamicConfigInspector $inspector): int
    {
        $schemaProfile = $this->option('profile') ?: null;
        $scopeTypeOpt  = $this->option('scope') ?: null;
        $scopeIdOpt    = $this->option('scope-id');
        $aggressive    = (bool) $this->option('aggressive');

        $scopes = $this->resolveScopes($scopeTypeOpt, $scopeIdOpt);

        if ($scopes === []) {
            $this->info('Нет доступных scope для обработки.');
            return self::SUCCESS;
        }

        $options = [
            'remove_unknown'    => $aggressive,
            'remove_deprecated' => $aggressive,
            'fix_aliases'       => true,
            'fix_normalizable'  => true,
            'fill_defaults'     => true,
        ];

        foreach ($scopes as $scope) {
            $this->line('');
            $this->info(sprintf(
                'Обработка scope %s:%s',
                $scope->type,
                $scope->id === null ? 'null' : (string) $scope->id
            ));

            $result = $inspector->autoFixScope($scope, $schemaProfile, $options);

            $fixed   = $result['fixed'] ?? [];
            $skipped = $result['skipped'] ?? [];

            $this->info(sprintf('  Исправлено: %d, пропущено: %d', count($fixed), count($skipped)));

            if ($fixed !== []) {
                $this->line('  Исправленные ключи:');
                foreach ($fixed as $row) {
                    $this->line(sprintf('    - %s (%s)', $row['key'], $row['action']));
                }
            }

            if ($aggressive && $skipped !== []) {
                $this->line('  Пропущенные проблемы:');
                foreach ($skipped as $row) {
                    $this->line(sprintf('    - %s (%s)', $row['key'] ?? '-', $row['reason'] ?? ''));
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * Определить список scope для обработки.
     */
    private function resolveScopes(?string $scopeTypeOpt, ?string $scopeIdOpt): array
    {
        if ($scopeTypeOpt !== null) {
            $type = strtolower($scopeTypeOpt);
            $id   = $scopeIdOpt !== null ? (int) $scopeIdOpt : null;

            return [$this->buildScope($type, $id)];
        }

        $rows = DB::table('dynamic_config_settings')
            ->select('scope_type', 'scope_id')
            ->distinct()
            ->get();

        $scopes = [Scope::global()];

        foreach ($rows as $row) {
            $type = strtolower((string) $row->scope_type);
            $id   = $row->scope_id !== null ? (int) $row->scope_id : null;

            $scopes[] = $this->buildScope($type, $id);
        }

        // Убираем дубликаты
        $unique = [];
        $result = [];

        foreach ($scopes as $scope) {
            $key = $scope->type . ':' . ($scope->id ?? 'null');

            if (isset($unique[$key])) {
                continue;
            }

            $unique[$key] = true;
            $result[]     = $scope;
        }

        return $result;
    }

    private function buildScope(string $type, ?int $id): Scope
    {
        return Scope::fromString($type, $id);
    }
}
