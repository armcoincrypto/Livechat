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
 *
 * Destination-required rails must not emit a normal NEW order card until
 * a usable deposit destination exists. Other rails keep existing behavior.
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

    /**
     * Destination-required rails (Kobbopay) may send the normal NEW operator
     * card only after a usable deposit destination exists.
     */
    public static function destinationAllowsCreatedNotification(string $owner, bool $hasAssignedDestination): bool
    {
        return ! PaymentDestinationFailClosedGuard::mustNotRemainPayable($owner, $hasAssignedDestination);
    }

    public function notifyCreated(Task $order): void
    {
        if (! $this->shouldNotifyCreated($order)) {
            Log::info('telegram_order_created_notify_skipped', [
                'order_id' => $order->id,
                'public_id' => $order->public_id ?? null,
                'status' => (int) ($order->status ?? 0),
                'reason' => 'destination_required_without_destination',
            ]);

            return;
        }

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

    private function shouldNotifyCreated(Task $order): bool
    {
        try {
            $order->loadMissing(['direction_exchange.currency1']);
            $owner = PaymentDestinationRouter::classifyDirection($order->direction_exchange);
        } catch (Throwable) {
            return true;
        }

        if (! PaymentDestinationFailClosedGuard::destinationRequired($owner)) {
            return true;
        }

        try {
            return InboundPaymentDestinationGuard::taskHasAssignedDestination($order);
        } catch (Throwable) {
            return false;
        }
    }
}
