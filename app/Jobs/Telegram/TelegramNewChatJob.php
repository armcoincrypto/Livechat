<?php

declare(strict_types=1);

namespace App\Jobs\Telegram;

use App\Jobs\Telegram\Concerns\TelegramNotificationReliability;
use App\Models\Task;
use App\Notifications\TelegramNewChatNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Order chat messages are intentionally multi-fire (one per chat event).
 * Bounded retry only — no send sentinel / uniqueness.
 */
class TelegramNewChatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use TelegramNotificationReliability;

    protected Task $order;

    protected ?string $message = null;

    public function __construct(Task $order, ?string $message = null)
    {
        $this->order = $order;
        $this->message = $message;
    }

    public function handle(): void
    {
        try {
            $this->order->notifyNow(new TelegramNewChatNotification($this->message));
        } catch (Throwable $e) {
            $this->telegramFailPermanentOrRetry($e, [
                'order_id' => $this->order->id,
                'event' => 'new_chat',
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->telegramLogDead($exception, [
            'order_id' => $this->order->id ?? null,
            'event' => 'new_chat',
        ]);
    }
}
