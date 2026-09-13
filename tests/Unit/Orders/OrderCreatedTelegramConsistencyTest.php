<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Models\Task;
use App\Services\Orders\OrderCreatedTelegramNotifier;
use App\Services\Orders\PaymentDestinationRouter;
use RuntimeException;
use Tests\TestCase;

/**
 * Operators must not receive a normal NEW order card for an order that
 * never became usably payable (destination-required Kobbopay rails).
 * No real Telegram API. No real Kobbopay.
 */
final class OrderCreatedTelegramConsistencyTest extends TestCase
{
    public function test_kobbopay_success_allows_normal_operator_notification(): void
    {
        $this->assertTrue(OrderCreatedTelegramNotifier::destinationAllowsCreatedNotification(
            PaymentDestinationRouter::OWNER_KOBBOPAY,
            true,
        ));
    }

    public function test_kobbopay_provider_failure_blocks_normal_operator_notification(): void
    {
        $this->assertFalse(OrderCreatedTelegramNotifier::destinationAllowsCreatedNotification(
            PaymentDestinationRouter::OWNER_KOBBOPAY,
            false,
        ));
    }

    public function test_non_required_manual_and_zelle_rails_preserve_operator_notification(): void
    {
        $this->assertTrue(OrderCreatedTelegramNotifier::destinationAllowsCreatedNotification(
            PaymentDestinationRouter::OWNER_EXSWAPING_REQUISITE,
            false,
        ));
        $this->assertTrue(OrderCreatedTelegramNotifier::destinationAllowsCreatedNotification(
            PaymentDestinationRouter::OWNER_ZELLE_VERIFICATION,
            false,
        ));
        $this->assertTrue(OrderCreatedTelegramNotifier::destinationAllowsCreatedNotification(
            PaymentDestinationRouter::OWNER_UNSUPPORTED,
            false,
        ));
    }

    public function test_success_path_sends_exactly_one_mocked_notification(): void
    {
        $calls = 0;
        $order = new Task();
        $order->id = 999101;
        $order->public_id = 1786800000101;
        $order->transfer_to_account = 'TSuccessWalletForNotifyTest';

        $notifier = new OrderCreatedTelegramNotifier(
            sender: function (string $event, Task $task) use (&$calls, $order): void {
                $calls++;
                $this->assertSame(OrderCreatedTelegramNotifier::EVENT, $event);
                $this->assertSame($order->id, $task->id);
            }
        );

        $notifier->notifyCreated($order);
        $this->assertSame(1, $calls);
    }

    public function test_kobbopay_with_destination_sends_exactly_one_mocked_notification(): void
    {
        $calls = 0;
        $order = $this->kobbopayTask(hasDest: true);

        $notifier = new OrderCreatedTelegramNotifier(
            sender: static function () use (&$calls): void {
                $calls++;
            }
        );

        $notifier->notifyCreated($order);
        $this->assertSame(1, $calls);
    }

    public function test_kobbopay_without_destination_does_not_call_sender(): void
    {
        $calls = 0;
        $order = $this->kobbopayTask(hasDest: false);

        $notifier = new OrderCreatedTelegramNotifier(
            sender: static function () use (&$calls): void {
                $calls++;
            }
        );

        $notifier->notifyCreated($order);
        $this->assertSame(0, $calls);
    }

    public function test_notifier_does_not_use_customer_mail_condition(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Orders/OrderCreatedTelegramNotifier.php');
        $this->assertStringNotContainsString('OrderCreatedCondition', $src);
        $this->assertStringContainsString('PaymentDestinationFailClosedGuard', $src);
        $this->assertStringContainsString('mustNotRemainPayable', $src);
        $this->assertStringContainsString('telegram_order_created_notify_skipped', $src);
        $this->assertStringNotContainsString("'email'", $src);
        $this->assertStringNotContainsString('wallet_number', $src);
        $this->assertStringNotContainsString('password', $src);
        $this->assertStringNotContainsString('bot_token', $src);
    }

    public function test_create_path_notifies_only_after_fail_closed(): void
    {
        $manager = $this->managerOrderSource();
        $created = $this->methodSource($manager, 'created');
        $addition = $this->methodSource($manager, 'additionInfo');

        $this->assertStringNotContainsString('notifyCreated', $addition);
        $this->assertStringNotContainsString('notifyOperatorCreatedTelegramIfEligible', $addition);
        $this->assertStringContainsString('abandonIfPaymentDestinationMissing', $created);
        $this->assertStringContainsString('notifyOperatorCreatedTelegramIfEligible', $created);
        $this->assertLessThan(
            strpos($created, 'notifyOperatorCreatedTelegramIfEligible') ?: PHP_INT_MAX,
            strpos($created, 'abandonIfPaymentDestinationMissing') ?: PHP_INT_MAX,
        );
        $abandonedReturnPos = strpos($created, 'if ($abandoned !== null)');
        $notifyPos = strpos($created, 'notifyOperatorCreatedTelegramIfEligible');
        $this->assertNotFalse($abandonedReturnPos);
        $this->assertNotFalse($notifyPos);
        $this->assertLessThan($notifyPos, $abandonedReturnPos);
        $this->assertSame(1, substr_count($manager, '->notifyCreated('));
        $this->assertSame(1, substr_count($manager, 'function notifyOperatorCreatedTelegramIfEligible'));
    }

    public function test_idempotent_reuse_does_not_dispatch_operator_telegram(): void
    {
        $created = $this->methodSource($this->managerOrderSource(), 'created');
        $reuseReturn = strpos($created, "'idempotent_reuse'");
        $notifyPos = strpos($created, 'notifyOperatorCreatedTelegramIfEligible');
        $this->assertNotFalse($reuseReturn);
        $this->assertNotFalse($notifyPos);
        $this->assertLessThan($notifyPos, $reuseReturn);
    }

    public function test_lifecycle_events_other_than_create_are_untouched(): void
    {
        $manager = $this->managerOrderSource();
        $this->assertStringNotContainsString("telegramNotificationForChannel('order_pay'", $manager);
        $this->assertStringNotContainsString("new_order_for_operator", $manager);

        $pay = (string) file_get_contents(dirname(__DIR__, 3).'/packages/ExchangerClient/Http/Controller/Operations/OrderPayController.php');
        $this->assertStringContainsString("telegramNotificationForChannel('new_order_for_operator'", $pay);
    }

    public function test_simulated_telegram_outage_still_fail_open(): void
    {
        $order = new Task();
        $order->id = 999102;
        $order->public_id = 1786800000102;
        $order->transfer_to_account = 'TSuccessWalletForNotifyTest';

        $notifier = new OrderCreatedTelegramNotifier(
            sender: static function (): void {
                throw new RuntimeException('simulated telegram outage');
            }
        );

        $notifier->notifyCreated($order);
        $this->assertTrue(true);
    }

    private function kobbopayTask(bool $hasDest): Task
    {
        $currency = new \App\Models\Currency();
        $currency->designation_xml = 'USDTTRC20';

        $direction = new \App\Models\DirectionExchange();
        $direction->setRelation('currency1', $currency);

        $order = new Task();
        $order->id = 999103;
        $order->public_id = 1786800000103;
        $order->transfer_to_account = $hasDest ? 'TAssignedDest' : '';
        $order->setRelation('direction_exchange', $direction);

        return $order;
    }

    private function managerOrderSource(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 3).'/packages/Order/Bindings/ManagerOrder.php');
    }

    private function methodSource(string $src, string $method): string
    {
        if (! preg_match('/function '.$method.'\b[\s\S]*?(?=\n    (?:public|protected|private) function |\n}\n?\z)/', $src, $m)) {
            $this->fail("method {$method} not found");
        }

        return $m[0];
    }
}
