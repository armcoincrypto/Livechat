<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Models\Task;
use App\Services\Orders\OrderCreatedTelegramNotifier;
use App\Services\Orders\TelegramNotificationSelector;
use RuntimeException;
use Tests\TestCase;

final class OrderCreatedTelegramNotifierTest extends TestCase
{
    public function test_selector_accepts_is_prefix_and_bare_event_keys(): void
    {
        $this->assertSame(
            ['process_created_order', 'is_process_created_order'],
            TelegramNotificationSelector::eventKeys('process_created_order')
        );
        $this->assertSame(
            ['is_process_created_order', 'process_created_order'],
            TelegramNotificationSelector::eventKeys('is_process_created_order')
        );
    }

    public function test_telegram_throw_after_persist_is_fail_open(): void
    {
        $order = new Task();
        $order->id = 999001;
        $order->public_id = 1786800000001;

        $notifier = new OrderCreatedTelegramNotifier(
            sender: static function (): void {
                throw new RuntimeException('simulated telegram outage');
            }
        );

        $notifier->notifyCreated($order);
        $this->assertTrue(true, 'operator Telegram exception must not propagate after persist');
    }

    public function test_manager_order_wraps_operator_telegram_fail_open(): void
    {
        $path = dirname(__DIR__, 3).'/packages/Order/Bindings/ManagerOrder.php';
        $src = (string) file_get_contents($path);
        $this->assertStringContainsString('OrderCreatedTelegramNotifier', $src);
        $this->assertStringContainsString('notifyCreated', $src);
        $this->assertStringNotContainsString(
            "iEXApp::telegramNotificationForChannel('process_created_order', \$this->getOrder());",
            $src
        );
    }

    public function test_order_pay_controller_does_not_surface_raw_telegram_on_create(): void
    {
        $path = dirname(__DIR__, 3).'/packages/ExchangerClient/Http/Controller/Operations/OrderPayController.php';
        $src = (string) file_get_contents($path);
        $this->assertStringContainsString('ORDER_INTERNAL_ERROR', $src);
        $this->assertStringContainsString('Ошибка обработки заказ', $src);
        $this->assertStringNotContainsString('Telegram bot', $src);
    }

    public function test_telegram_order_job_has_bounded_retry_and_selector(): void
    {
        $path = dirname(__DIR__, 3).'/app/Jobs/Telegram/TelegramOrderJob.php';
        $src = (string) file_get_contents($path);
        $this->assertStringContainsString('public int $tries = 3', $src);
        $this->assertStringContainsString('TelegramNotificationSelector', $src);
        $this->assertStringContainsString('telegram_notify_sent:', $src);
        $this->assertStringContainsString('notifyNow', $src);
        $this->assertStringContainsString('onQueue(\'high\')', $src);
    }
}
