<?php

declare(strict_types=1);

namespace iEXPackages\OrderRecount;

use App\Models\Task;
use iEXPackages\OrderRecount\Services\OrderRecountEngine;

/**
 * OrderRecountManager
 *
 * Публичная точка входа модуля OrderRecount.
 * Используется Facade OrderRecount.
 */
final class OrderRecountManager
{
    public function __construct(
        private readonly OrderRecountEngine $engine,
    ) {}

    /**
     * Выполнить пересчёт заявки.
     *
     * @param Task $task
     * @param string $triggerType status-change | cron | manual
     * @param int|null $currentStatus
     */
    public function execute(Task $task, string $triggerType, ?int $currentStatus = null): void
    {
        $this->engine->execute($task, $triggerType, $currentStatus);
    }
}
