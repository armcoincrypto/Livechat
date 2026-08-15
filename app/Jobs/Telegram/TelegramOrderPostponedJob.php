<?php
declare(strict_types=1);

namespace App\Jobs\Telegram;

use App\Models\Task;
use App\Notifications\TelegramOrderPostponedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TelegramOrderPostponedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Детали заявки
     *
     * @var Task
     */
    protected Task $order;

    /**
     * Create a new job instance.
     */
    public function __construct(Task $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function handle(): void
    {
        $this->order->notify(new TelegramOrderPostponedNotification());
    }
}
