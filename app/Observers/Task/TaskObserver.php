<?php
declare(strict_types=1);

namespace App\Observers\Task;

use App\Models\Task;
use App\Services\Analytics\AttributionEventRecorder;
use Carbon\Carbon;
use iEXPackages\Transaction\Facades\TransactionFacade;
use Illuminate\Support\Facades\Log;

class TaskObserver
{
    /**
     * Слушаем созданное пользователем событие.
     *
     * @param Task $task
     */
    public function creating(Task $task): void
    {
        if ($task->next_checkout_at === null) {
            $task->next_checkout_at = Carbon::now()->addMinute();
        }
    }

    public function updating(Task $task): void
    {
        if ($task->isDirty('status')) {
            $newStatus = (int) $task->status;
            $oldStatus = (int) $task->getOriginal('status');

            $successfulStatuses = [4];

            if (!in_array($oldStatus, $successfulStatuses, true)
                && in_array($newStatus, $successfulStatuses, true)
                && $task->completed_at === null
            ) {
                $task->completed_at = Carbon::now();
            }
        }
    }

    public function updated(Task $task): void
    {
        if (! $task->wasChanged('status')) {
            return;
        }

        iex_order_status_log(
            $task,
            (int) $task->getOriginal('status'),
            (int) $task->status
        );

        // C.3E — best-effort lifecycle event after authoritative status commit.
        // Must never throw into the financial status path.
        try {
            app(AttributionEventRecorder::class)
                ->recordStatusTransitionFailOpen($task, (int) $task->status);
        } catch (\Throwable) {
            // never break status transitions
        }
    }
}
