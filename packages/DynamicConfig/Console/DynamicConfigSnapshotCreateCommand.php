<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Console;

use iEXPackages\DynamicConfig\Services\DynamicConfigSnapshotService;
use iEXPackages\DynamicConfig\ValueObjects\Scope;
use Illuminate\Console\Command;

final class DynamicConfigSnapshotCreateCommand extends Command
{
    protected $signature = 'dynamic-config:snapshot
        {name : Имя снапшота}
        {--scope=global : Тип scope (global, project, client, user...)}
        {--scope-id= : ID scope (если есть)}
        {--merged : Сохранять merged-настройки (с учётом родителей)}
    ';

    protected $description = 'Создать снапшот настроек DynamicConfig для scope.';

    public function handle(DynamicConfigSnapshotService $service): int
    {
        $name      = (string) $this->argument('name');
        $scopeType = (string) $this->option('scope');
        $scopeId   = $this->option('scope-id') !== null ? (int) $this->option('scope-id') : null;
        $merged    = (bool) $this->option('merged');

        $scope = Scope::fromString($scopeType, $scopeId);

        $id = $service->createSnapshot($scope, $name, $merged);

        $this->info(sprintf('Снапшот #%d успешно создан.', $id));

        return self::SUCCESS;
    }
}
