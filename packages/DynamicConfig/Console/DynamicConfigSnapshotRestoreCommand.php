<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Console;

use iEXPackages\DynamicConfig\Services\DynamicConfigSnapshotService;
use Illuminate\Console\Command;

final class DynamicConfigSnapshotRestoreCommand extends Command
{
    protected $signature = 'dynamic-config:restore
        {id : ID снапшота}
    ';

    protected $description = 'Восстановить настройки из снапшота DynamicConfig.';

    public function handle(DynamicConfigSnapshotService $service): int
    {
        $id = (int) $this->argument('id');

        $service->restoreSnapshot($id);

        $this->info(sprintf('Снапшот #%d восстановлен.', $id));

        return self::SUCCESS;
    }
}
