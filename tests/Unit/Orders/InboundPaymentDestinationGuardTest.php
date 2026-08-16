<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Models\DirectionExchange;
use App\Models\Task;
use App\Services\Orders\InboundPaymentDestinationGuard;
use Tests\TestCase;

final class InboundPaymentDestinationGuardTest extends TestCase
{
    public function test_error_code_is_stable(): void
    {
        $this->assertSame(
            'PAYMENT_DESTINATION_UNAVAILABLE',
            InboundPaymentDestinationGuard::ERROR_PAYMENT_DESTINATION_UNAVAILABLE
        );
    }

    public function test_usdc_erc20_direction_currently_has_no_active_source(): void
    {
        $dir = DirectionExchange::query()->find(718);
        $this->assertNotNull($dir);
        $this->assertFalse(InboundPaymentDestinationGuard::directionHasSource($dir));
    }

    public function test_usdt_trc20_and_btc_directions_have_a_source(): void
    {
        $usdt = DirectionExchange::query()->find(15);
        $btc = DirectionExchange::query()->find(14);
        $this->assertNotNull($usdt);
        $this->assertNotNull($btc);
        $this->assertTrue(InboundPaymentDestinationGuard::directionHasSource($usdt));
        $this->assertTrue(InboundPaymentDestinationGuard::directionHasSource($btc));
    }

    public function test_incident_order_has_no_assigned_destination(): void
    {
        $task = Task::query()->where('public_id', 1786828013012)->first();
        $this->assertNotNull($task);
        $this->assertFalse(InboundPaymentDestinationGuard::taskHasAssignedDestination($task));
    }

    public function test_validate_order_gates_missing_destination_before_persist(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/packages/Order/Concerns/ValidatesOrderRules.php');
        $this->assertStringContainsString('InboundPaymentDestinationGuard::directionHasSource', $src);
        $this->assertStringContainsString('PAYMENT_DESTINATION_UNAVAILABLE', $src);
        $this->assertStringContainsString('VERIFICATION_REQUIRED', $src);
    }

    public function test_process_resource_does_not_mark_wallet_issued_without_destination(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/packages/ExchangerClient/Http/Resources/Orders/OrderProcessResource.php');
        $this->assertStringContainsString('payment_destination_ready', $src);
        $this->assertStringContainsString('$invoiceHasDest', $src);
        $this->assertStringContainsString('resolve($task, false)', $src);
        $this->assertStringContainsString("update(['is_wallet_issued' => 1])", $src);
    }

    public function test_process_and_mail_use_snapshot_not_lazy_issue(): void
    {
        $manager = (string) file_get_contents(dirname(__DIR__, 3).'/packages/Order/Invoices/RequisiteManager.php');
        $invoice = (string) file_get_contents(dirname(__DIR__, 3).'/packages/Order/Services/InvoiceContextService.php');
        $mail = (string) file_get_contents(dirname(__DIR__, 3).'/packages/SmartMailer/Dispatches/Orders/OrderCreatedMail.php');
        $create = (string) file_get_contents(dirname(__DIR__, 3).'/packages/Order/Bindings/ManagerOrder.php');

        $this->assertStringContainsString('function snapshot()', $manager);
        $this->assertStringContainsString('payment_destination_snapshot_empty', $manager);
        $this->assertStringContainsString('bool $allowIssue = true', $invoice);
        $this->assertStringContainsString('$manager->snapshot()', $invoice);
        $this->assertStringContainsString('->snapshot()', $mail);
        $this->assertStringContainsString('->resolve($order)', $create);
        $this->assertStringNotContainsString('resolve($order, false)', $create);
    }

    public function test_incident_order_snapshot_does_not_assign_destination(): void
    {
        $task = Task::query()->where('public_id', 1786828013012)->first();
        $this->assertNotNull($task);
        $beforeAccount = (string) ($task->transfer_to_account ?? '');
        $beforeIssued = (int) ($task->is_wallet_issued ?? 0);
        $beforeReq = (int) ($task->id_payment_requisites ?? 0);

        $ctx = app(\iEXPackages\Order\Services\InvoiceContextService::class)->resolve($task, false);
        $task->refresh();

        $this->assertSame($beforeAccount, (string) ($task->transfer_to_account ?? ''));
        $this->assertSame($beforeIssued, (int) ($task->is_wallet_issued ?? 0));
        $this->assertSame($beforeReq, (int) ($task->id_payment_requisites ?? 0));
        $this->assertSame('none', (string) ($ctx['mode'] ?? ''));
        $this->assertTrue(empty($ctx['account']));
    }
}
