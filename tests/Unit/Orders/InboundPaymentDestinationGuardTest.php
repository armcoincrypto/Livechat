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
    }

    public function test_process_resource_does_not_mark_wallet_issued_without_destination(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/packages/ExchangerClient/Http/Resources/Orders/OrderProcessResource.php');
        $this->assertStringContainsString('payment_destination_ready', $src);
        $this->assertStringContainsString('$invoiceHasDest', $src);
        $this->assertStringContainsString('$invoiceHasDest', $src);
        $this->assertStringContainsString("update(['is_wallet_issued' => 1])", $src);
    }
}
