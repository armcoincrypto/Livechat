<?php

declare(strict_types=1);

namespace iEXPackages\OrderRecount\Console;

use App\Models\Task;
use iEXPackages\OrderRecount\Services\OrderRecountEngine;
use Illuminate\Console\Command;

final class OrderRecountExplainCommand extends Command
{
    protected $signature = 'order-recount:explain {taskId} {--trigger=manual} {--status=}';
    protected $description = 'OrderRecount: explain decision for a task (writes audit)';

    public function handle(OrderRecountEngine $engine): int
    {
        $taskId = (int)$this->argument('taskId');
        $trigger = (string)$this->option('trigger');
        $statusOpt = $this->option('status');

        $task = Task::query()->with(['direction_exchange.currency1'])->find($taskId);
        if (!$task) {
            $this->error("Task #{$taskId} not found");
            return self::FAILURE;
        }

        $status = $statusOpt !== null ? (int)$statusOpt : (int)$task->status;
        $engine->execute($task, $trigger, $status);

        $this->info('Done. Check order_recount_audit.');
        return self::SUCCESS;
    }
}
