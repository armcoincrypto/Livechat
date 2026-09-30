<?php

declare(strict_types=1);

namespace App\Jobs\Telegram;

use App\Jobs\Telegram\Concerns\TelegramNotificationReliability;
use App\Models\Task;
use App\Models\TelegramNotification;
use App\Notifications\TelegramAutoPaymentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class TelegramAutoPaymentJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use TelegramNotificationReliability;

    public int $uniqueFor = 300;

    protected Task $order;

    protected string $provider;

    public function __construct(Task $order, string $provider)
    {
        $this->order = $order;
        $this->provider = $provider;
    }

    public function uniqueId(): string
    {
        return 'telegram-auto-payment:'.$this->order->id.':'.$this->provider;
    }

    public function handle(): void
    {
        $sentKey = $this->telegramSentKey('order', $this->order->id, 'order_pay:'.$this->provider);
        if ($this->telegramAlreadySent($sentKey)) {
            Log::info('telegram_auto_payment_duplicate_skipped', [
                'order_id' => $this->order->id,
                'provider' => $this->provider,
            ]);

            return;
        }

        $notifications = TelegramNotification::where('status', 1)->where('ext_params->is_order_pay', 1)->get();
        $anyOk = false;
        foreach ($notifications as $notification) {
            try {
                Notification::route('telegram', $notification->id_channel)
                    ->notifyNow(new TelegramAutoPaymentNotification($notification, $this->order, $this->provider));
                $anyOk = true;
            } catch (Throwable $e) {
                $this->telegramFailPermanentOrRetry($e, [
                    'order_id' => $this->order->id,
                    'provider' => $this->provider,
                    'channel_id' => $notification->id,
                ]);

                return;
            }
        }

        if ($anyOk) {
            $this->telegramMarkSent($sentKey);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->telegramLogDead($exception, [
            'order_id' => $this->order->id ?? null,
            'provider' => $this->provider ?? null,
            'event' => 'order_pay',
        ]);
    }
}
