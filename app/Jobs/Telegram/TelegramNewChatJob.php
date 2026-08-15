<?php
declare(strict_types=1);

namespace App\Jobs\Telegram;

use App\Models\Task;
use App\Notifications\TelegramNewChatNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TelegramNewChatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Детали заявки
     *
     * @var Task
     */
    protected Task $order;

    protected ?string $message = null;

    /**
     * Create a new job instance.
     */
    public function __construct(Task $order, ?string $message = null)
    {
        $this->order = $order;
        $this->message = $message;
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
        $this->order->notify(new TelegramNewChatNotification($this->message));
    }
}
