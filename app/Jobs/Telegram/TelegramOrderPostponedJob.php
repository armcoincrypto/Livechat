<?php

declare(strict_types=1);

namespace App\Jobs\Telegram;

use App\Jobs\Telegram\Concerns\TelegramNotificationReliability;
use App\Models\Task;
use App\Notifications\TelegramOrderPostponedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramOrderPostponedJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use TelegramNotificationReliability;

    public int $uniqueFor = 300;

    protected Task $order;

    public function __construct(Task $order)
    {
        $this->order = $order;
    }

    public function uniqueId(): string
    {
        return 'telegram-order-postponed:'.$this->order->id;
    }

    public function handle(): void
    {
        $sentKey = $this->telegramSentKey('order', $this->order->id, 'postponed');
        if ($this->telegramAlreadySent($sentKey)) {
            Log::info('telegram_order_postponed_duplicate_skipped', [
                'order_id' => $this->order->id,
            ]);

            return;
        }

        try {
            $this->order->notifyNow(new TelegramOrderPostponedNotification());
            $this->telegramMarkSent($sentKey);
        } catch (Throwable $e) {
            $this->telegramFailPermanentOrRetry($e, [
                'order_id' => $this->order->id,
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->telegramLogDead($exception, [
            'order_id' => $this->order->id ?? null,
            'event' => 'postponed',
        ]);
    }
}
