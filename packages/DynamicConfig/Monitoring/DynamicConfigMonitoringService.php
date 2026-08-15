<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Monitoring;

use iEXPackages\DynamicConfig\Inspection\DynamicConfigInspector;
use iEXPackages\DynamicConfig\Schema\ConfigSchemaRegistry;
use iEXPackages\DynamicConfig\ValueObjects\Scope;
use Illuminate\Support\Facades\DB;

/**
 * DynamicConfigMonitoringService
 *
 * Формирует сводку:
 *  - по каждому scope: количество ключей, проблем, статистика по группам;
 *  - по миграциям настроек.
 */
final class DynamicConfigMonitoringService
{
    public function __construct(
        private readonly DynamicConfigInspector $inspector,
        private readonly ConfigSchemaRegistry $schemaRegistry
    ) {
    }

    /**
     * Полная сводка по всем scope.
     */
    public function getSummary(?string $schemaProfile = null): array
    {
        $scopes = $this->getAllScopes();

        $scopesSummary = [];

        foreach ($scopes as $scope) {
            $issues   = $this->inspector->inspectScope($scope, $schemaProfile);
            $issueMap = $this->countIssuesByType($issues);
            $groupMap = $this->countKeysByGroup($scope, $schemaProfile);
            $keys     = $this->getSettingsCount($scope);

            $scopesSummary[] = [
                'scope_type'        => $scope->type,
                'scope_id'          => $scope->id,
                'total_keys'        => $keys['total_keys'],
                'unknown_keys'      => $issueMap['unknown_key']      ?? 0,
                'deprecated_keys'   => $issueMap['deprecated']       ?? 0,
                'validation_errors' => $issueMap['validation_error'] ?? 0,
                'missing_required'  => $issueMap['missing_required'] ?? 0,
                'groups'            => $groupMap,
            ];
        }

        $migrations = $this->getMigrationsSummary();

        return [
            'generated_at' => now()->toDateTimeString(),
            'scopes'       => $scopesSummary,
            'migrations'   => $migrations,
        ];
    }

    /**
     * Считает проблемы по типам.
     */
    private function countIssuesByType(array $issues): array
    {
        $result = [];

        foreach ($issues as $issue) {
            $type = $issue['issue_type'] ?? 'unknown';
            $result[$type] = ($result[$type] ?? 0) + 1;
        }

        return $result;
    }

    /**
     * Количество ключей по группам (group из schema).
     */
    private function countKeysByGroup(Scope $scope, ?string $schemaProfile = null): array
    {
        $rows = DB::table('dynamic_config_settings')
            ->where('scope_type', $scope->type)
            ->where('scope_id', $scope->id)
            ->get(['key']);

        $groups = [];

        foreach ($rows as $row) {
            $key = (string) $row->key;
            $def = $this->schemaRegistry->findDefinition($key, $schemaProfile);
            $group = $def['group'] ?? 'other';

            $groups[$group] = ($groups[$group] ?? 0) + 1;
        }

        return $groups;
    }

    private function getSettingsCount(Scope $scope): array
    {
        $count = (int) DB::table('dynamic_config_settings')
            ->where('scope_type', $scope->type)
            ->where('scope_id', $scope->id)
            ->count();

        return ['total_keys' => $count];
    }

    /**
     * Все scope, которые есть в dynamic_config_settings (+ global).
     *
     * @return Scope[]
     */
    private function getAllScopes(): array
    {
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

    private function getMigrationsSummary(): array
    {
        $total = (int) DB::table('dynamic_config_migrations')->count();
        $last  = DB::table('dynamic_config_migrations')->orderByDesc('id')->first();

        return [
            'total'       => $total,
            'last_batch'  => $last?->batch ?? null,
            'last_run_at' => $last?->created_at ? (string) $last->created_at : null,
        ];
    }
}
