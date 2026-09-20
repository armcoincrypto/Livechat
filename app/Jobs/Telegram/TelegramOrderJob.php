<?php

namespace App\Jobs\Telegram;

use App\Models\Task;
use App\Notifications\TelegramNewOrder;
use App\Services\Orders\TelegramNotificationSelector;
use App\Services\TelegramOperator\TelegramOrderLifecyclePublisher;
use App\Services\TelegramOperator\TelegramOperatorAuthService;
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

    public function handle(
        TelegramNotificationSelector $selector,
        TelegramOperatorAuthService $auth,
        TelegramOrderLifecyclePublisher $lifecycle,
    ): void {
        if ($this->isCreatedEvent($this->param)) {
            $this->handleLifecycleCreated($lifecycle);

            return;
        }

        if ($this->isPaidOperatorEvent($this->param)) {
            $this->handleLifecyclePaid($lifecycle);

            return;
        }

        $this->handleLegacySend($selector, $auth);
    }

    private function handleLifecycleCreated(TelegramOrderLifecyclePublisher $lifecycle): void
    {
        $sentKey = 'telegram_notify_sent:'.$this->order->id.':lifecycle_new';
        if (Cache::get($sentKey)) {
            Log::info('telegram_order_notify_duplicate_skipped', [
                'order_id' => $this->order->id,
                'notification_type' => $this->param,
                'lifecycle' => 'new',
            ]);

            return;
        }

        $stats = $lifecycle->publishNew($this->order->fresh() ?? $this->order);
        if (($stats['sent'] + $stats['edited'] + $stats['recovered']) > 0) {
            Cache::put($sentKey, 1, now()->addDays(7));
        } else {
            throw new \RuntimeException('telegram_order_lifecycle_new_failed');
        }
    }

    private function handleLifecyclePaid(TelegramOrderLifecyclePublisher $lifecycle): void
    {
        // Idempotent EDIT of the existing card — do not SEND a second full card.
        $stats = $lifecycle->publishPaid($this->order->fresh() ?? $this->order);
        if (($stats['sent'] + $stats['edited'] + $stats['recovered']) === 0) {
            throw new \RuntimeException('telegram_order_lifecycle_paid_failed');
        }
    }

    private function handleLegacySend(
        TelegramNotificationSelector $selector,
        TelegramOperatorAuthService $auth,
    ): void {
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

        $dmEnabled = filter_var(
            config('telegram_operator.notify_operator_dms', true),
            FILTER_VALIDATE_BOOLEAN
        );
        $dmTargets = $dmEnabled
            ? $auth->authorizedActionTelegramUserIds()
            : [];

        $anyOk = false;
        $channelFailed = false;

        foreach ($notifications as $notification) {
            $channelChat = (string) $notification->id_channel;
            try {
                Notification::route('telegram', $channelChat)
                    ->notifyNow(new TelegramNewOrder($notification, $this->order, $channelChat));
                $anyOk = true;
                Log::info('telegram_order_notify_success', [
                    'order_id' => $this->order->id,
                    'notification_type' => $this->param,
                    'channel_id' => $notification->id,
                    'destination' => 'channel',
                    'chat_id' => $channelChat,
                    'attempt' => $this->attempts(),
                ]);
            } catch (Throwable $e) {
                $channelFailed = true;
                Log::warning('telegram_order_notify_failure', [
                    'order_id' => $this->order->id,
                    'notification_type' => $this->param,
                    'channel_id' => $notification->id,
                    'destination' => 'channel',
                    'chat_id' => $channelChat,
                    'attempt' => $this->attempts(),
                    'exception_class' => $e::class,
                ]);
            }

            foreach ($dmTargets as $telegramUserId) {
                $dmChat = (string) $telegramUserId;
                try {
                    Notification::route('telegram', $dmChat)
                        ->notifyNow(new TelegramNewOrder($notification, $this->order, $dmChat));
                    $anyOk = true;
                    Log::info('telegram_order_notify_success', [
                        'order_id' => $this->order->id,
                        'notification_type' => $this->param,
                        'channel_id' => $notification->id,
                        'destination' => 'operator_dm',
                        'chat_id_suffix' => substr($dmChat, -3),
                        'attempt' => $this->attempts(),
                    ]);
                } catch (Throwable $e) {
                    Log::warning('telegram_order_notify_failure', [
                        'order_id' => $this->order->id,
                        'notification_type' => $this->param,
                        'channel_id' => $notification->id,
                        'destination' => 'operator_dm',
                        'chat_id_suffix' => substr($dmChat, -3),
                        'attempt' => $this->attempts(),
                        'exception_class' => $e::class,
                    ]);
                }
            }
        }

        if ($anyOk) {
            Cache::put($sentKey, 1, now()->addDays(7));
        }

        if ($channelFailed && ! $anyOk) {
            throw new \RuntimeException('telegram_order_notify_all_destinations_failed');
        }
    }

    private function isCreatedEvent(string $param): bool
    {
        $keys = TelegramNotificationSelector::eventKeys($param);

        return in_array('process_created_order', $keys, true)
            || in_array('is_process_created_order', $keys, true);
    }

    private function isPaidOperatorEvent(string $param): bool
    {
        $keys = TelegramNotificationSelector::eventKeys($param);

        return in_array('new_order_for_operator', $keys, true)
            || in_array('is_new_order_for_operator', $keys, true);
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
