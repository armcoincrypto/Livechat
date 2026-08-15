<?php

namespace App\Jobs\Telegram;

use App\Models\Task;
use App\Notifications\TelegramNewOrder;
use App\Services\Orders\TelegramNotificationSelector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class TelegramOrderJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    public int $timeout = 20;

    public int $uniqueFor = 120;

    protected Task $order;

    public function __construct(Task $order, public string $param)
    {
        $this->order = $order;
        $this->onQueue('high');
    }

    public function uniqueId(): string
    {
        return 'telegram-order:'.$this->order->id.':'.$this->param;
    }

    public function handle(TelegramNotificationSelector $selector): void
    {
        $sentKey = 'telegram_notify_sent:'.$this->order->id.':'.$this->param;
        if (Cache::get($sentKey)) {
            Log::info('telegram_order_notify_duplicate_skipped', [
                'order_id' => $this->order->id,
                'notification_type' => $this->param,
            ]);

            return;
        }

        $notifications = $selector->enabledForEvent($this->param);
        if ($notifications->isEmpty()) {
            Log::warning('telegram_order_notify_no_channel', [
                'order_id' => $this->order->id,
                'notification_type' => $this->param,
            ]);

            return;
        }

        $anyOk = false;
        foreach ($notifications as $notification) {
            try {
                Notification::route('telegram', $notification->id_channel)
                    ->notifyNow(new TelegramNewOrder($notification, $this->order));
                $anyOk = true;
                Log::info('telegram_order_notify_success', [
                    'order_id' => $this->order->id,
                    'notification_type' => $this->param,
                    'channel_id' => $notification->id,
                    'attempt' => $this->attempts(),
                ]);
            } catch (Throwable $e) {
                Log::warning('telegram_order_notify_failure', [
                    'order_id' => $this->order->id,
                    'notification_type' => $this->param,
                    'channel_id' => $notification->id,
                    'attempt' => $this->attempts(),
                    'exception_class' => $e::class,
                ]);
                throw $e;
            }
        }

        if ($anyOk) {
            Cache::put($sentKey, 1, now()->addDays(7));
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('telegram_order_notify_dead', [
            'order_id' => $this->order->id,
            'notification_type' => $this->param,
            'exception_class' => $exception ? $exception::class : null,
        ]);
    }
}
