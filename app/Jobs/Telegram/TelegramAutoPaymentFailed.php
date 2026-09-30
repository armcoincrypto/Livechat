<?php

declare(strict_types=1);

namespace App\Jobs\Telegram;

use App\Jobs\Telegram\Concerns\TelegramNotificationReliability;
use App\Models\Task;
use App\Models\TelegramNotification;
use App\Notifications\TelegramPaymentFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class TelegramAutoPaymentFailed implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use TelegramNotificationReliability;

    public int $uniqueFor = 300;

    protected Task $order;

    protected string|null $error_text;

    public function __construct(Task $order, string|null $error = null)
    {
        $this->order = $order;
        $this->error_text = $error;
    }

    public function uniqueId(): string
    {
        return 'telegram-auto-payment-failed:'.$this->order->id;
    }

    public function handle(): void
    {
        $sentKey = $this->telegramSentKey('order', $this->order->id, 'failed_for_pay');
        if ($this->telegramAlreadySent($sentKey)) {
            Log::info('telegram_auto_payment_failed_duplicate_skipped', [
                'order_id' => $this->order->id,
            ]);

            return;
        }

        $notifications = TelegramNotification::where('status', 1)->where('ext_params->is_failed_for_pay', 1)->get();
        $anyOk = false;
        foreach ($notifications as $notification) {
            try {
                Notification::route('telegram', $notification->id_channel)
                    ->notifyNow(new TelegramPaymentFailed($notification, $this->order, $this->error_text));
                $anyOk = true;
            } catch (Throwable $e) {
                $this->telegramFailPermanentOrRetry($e, [
                    'order_id' => $this->order->id,
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
            'event' => 'failed_for_pay',
        ]);
    }
}
