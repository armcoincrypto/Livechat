<?php

declare(strict_types=1);

namespace iEXPackages\OrderRecount\Jobs;

use App\Models\Task;
use iEXPackages\OrderRecount\Facades\OrderRecount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RecountTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $taskId,
        public readonly string $triggerType,
        public readonly ?int $currentStatus = null,
    ) {
        $this->onQueue((string) config('order-recount.queue.recount', 'default'));
    }

    public function handle(): void
    {
        $task = Task::query()
            ->with(['direction_exchange.currency1'])
            ->find($this->taskId);

        if (!$task) {
            return;
        }

        OrderRecount::execute($task, $this->triggerType, $this->currentStatus);
    }
}
