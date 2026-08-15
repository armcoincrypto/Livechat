<?php

declare(strict_types=1);

namespace iEXPackages\OrderRecount\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void execute(\App\Models\Task $task, string $triggerType, ?int $currentStatus = null)
 */
final class OrderRecount extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'order-recount';
    }
}
