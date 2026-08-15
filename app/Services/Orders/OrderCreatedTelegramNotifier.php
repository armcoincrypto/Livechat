<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Task;
use App\Support\Facades\iEXApp;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Operator Telegram on order create is an operational notification.
 * Order persistence must not be rolled back or reported as HTTP failure
 * when Telegram dispatch throws.
 */
final class OrderCreatedTelegramNotifier
{
    public const EVENT = 'process_created_order';

    /**
     * @param  callable(string, Task):void|null  $sender
     */
    public function __construct(
        private $sender = null,
    ) {
    }

    public function notifyCreated(Task $order): void
    {
        try {
            $send = $this->sender ?? static function (string $event, Task $task): void {
                iEXApp::telegramNotificationForChannel($event, $task);
            };
            $send(self::EVENT, $order);
        } catch (Throwable $e) {
            Log::warning('telegram_order_created_notify_failed', [
                'order_id' => $order->id,
                'public_id' => $order->public_id ?? null,
                'notification_type' => self::EVENT,
                'exception_class' => $e::class,
            ]);
        }
    }
}
