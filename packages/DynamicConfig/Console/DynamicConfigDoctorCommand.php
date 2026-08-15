<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Console;

use iEXPackages\DynamicConfig\Inspection\DynamicConfigInspector;
use iEXPackages\DynamicConfig\ValueObjects\Scope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Config Integrity Checker ("doctor").
 *
 * Примеры:
 *  php artisan dynamic-config:doctor
 *  php artisan dynamic-config:doctor --scope=license --scope-id=5
 *  php artisan dynamic-config:doctor --profile=default
 */
final class DynamicConfigDoctorCommand extends Command
{
    protected $signature = 'dynamic-config:doctor
        {--scope= : Тип scope (global, license, exchange, user, пусто = все)}
        {--scope-id= : ID scope (если нужно ограничить одним scope)}
        {--profile= : Профиль схемы (schemaProfile)}
    ';

    protected $description = 'Проверка целостности и соответствия настроек DynamicConfig схеме.';

    public function handle(DynamicConfigInspector $inspector): int
    {
        $schemaProfile = $this->option('profile') ?: null;
        $scopeTypeOpt  = $this->option('scope') ?: null;
        $scopeIdOpt    = $this->option('scope-id');

        $scopes = $this->resolveScopes($scopeTypeOpt, $scopeIdOpt);

        if ($scopes === []) {
            $this->info('Нет доступных scope для проверки.');
            return self::SUCCESS;
        }

        $totalIssues = 0;

        foreach ($scopes as $scope) {
            $this->line('');
            $this->info(sprintf(
                'Проверка scope %s:%s',
                $scope->type,
                $scope->id === null ? 'null' : (string) $scope->id
            ));

            $issues = $inspector->inspectScope($scope, $schemaProfile);

            if ($issues === []) {
                $this->info('  ✓ Проблем не обнаружено.');
                continue;
            }

            $totalIssues += count($issues);
            $this->warn(sprintf('  Найдено проблем: %d', count($issues)));

            foreach ($issues as $issue) {
                $type    = $issue['issue_type'];
                $key     = $issue['key'];
                $message = $issue['message'];

                $this->line(sprintf(
                    '    - [%s] %s: %s',
                    $type,
                    $key,
                    $message
                ));
            }
        }

        $this->line('');
        if ($totalIssues === 0) {
            $this->info('DynamicConfig Doctor: все проверенные scope выглядят корректно ✅');
        } else {
            $this->warn(sprintf('DynamicConfig Doctor: всего проблем по всем scope: %d', $totalIssues));
        }

        return self::SUCCESS;
    }

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
