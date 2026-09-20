<?php
declare(strict_types=1);

namespace App\Observers\Task;

use App\Jobs\Telegram\TelegramOrderJob;
use App\Models\Task;
use App\Services\Analytics\AttributionEventRecorder;
use App\Services\TelegramOperator\TelegramOrderMessagePresenter;
use Carbon\Carbon;
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

        $this->dispatchTerminalOperatorCardFailOpen($task);
    }

    /**
     * EDIT the canonical operator Telegram card on terminal status changes.
     * Fail-open: never break the financial status path.
     */
    private function dispatchTerminalOperatorCardFailOpen(Task $task): void
    {
        $param = match ((int) $task->status) {
            1 => 'lifecycle_expired',
            5 => 'lifecycle_rejected',
            6 => 'lifecycle_cancelled',
            default => null,
        };
        if ($param === null) {
            return;
        }

        try {
            $life = TelegramOrderMessagePresenter::lifecycleFromStatus((int) $task->status);
            if (! TelegramOrderMessagePresenter::isTerminalLifecycle($life)
                || $life === TelegramOrderMessagePresenter::LIFECYCLE_COMPLETED
            ) {
                return;
            }
            TelegramOrderJob::dispatch($task, $param);
        } catch (\Throwable $e) {
            Log::warning('telegram_order_terminal_dispatch_failed', [
                'task_id' => $task->id,
                'public_id' => $task->public_id ?? null,
                'status' => (int) $task->status,
                'exception_class' => $e::class,
            ]);
        }
    }
}
