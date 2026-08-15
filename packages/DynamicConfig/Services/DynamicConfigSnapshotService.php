<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Services;

use iEXPackages\DynamicConfig\DynamicConfigManager;
use iEXPackages\DynamicConfig\ValueObjects\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Создание и восстановление снапшотов настроек.
 */
final class DynamicConfigSnapshotService
{
    public function __construct(
        private readonly DynamicConfigManager $configManager
    ) {
    }

    /**
     * Создать снапшот для scope.
     *
     * @return int ID снапшота
     */
    public function createSnapshot(
        Scope $scope,
        string $name,
        bool $merged = false
    ): int {
        $settings = $this->configManager->all($scope, merged: $merged);

        $json = json_encode(
            $settings,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $id = DB::table('dynamic_config_snapshots')->insertGetId([
            'name'       => $name,
            'scope_type' => $scope->type,
            'scope_id'   => $scope->id,
            'data'       => $json,
            'created_by' => Auth::id(),
            'created_at' => now(),
        ]);

        return (int) $id;
    }

    /**
     * Восстановить снапшот (полная замена настроек scope).
     */
    public function restoreSnapshot(int $snapshotId): void
    {
        $row = DB::table('dynamic_config_snapshots')
            ->where('id', $snapshotId)
            ->first();

        if (!$row) {
            return;
        }

        $scope = Scope::fromString((string) $row->scope_type, $row->scope_id !== null ? (int) $row->scope_id : null);

        $data = json_decode($row->data, true);

        if (!is_array($data)) {
            return;
        }

        // Полностью заменяем настройки этого scope
        $this->configManager->clearScope($scope);
        $this->configManager->update($data, $scope);
    }
}
