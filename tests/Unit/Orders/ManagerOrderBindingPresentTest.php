<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use Tests\TestCase;

final class ManagerOrderBindingPresentTest extends TestCase
{
    public function test_manager_order_binding_exists_and_order_class_loads(): void
    {
        $root = dirname(__DIR__, 3);
        $path = $root.'/packages/Order/Bindings/ManagerOrder.php';
        $this->assertFileExists($path, 'ManagerOrder.php missing — /order create cannot autoload');

        $src = (string) file_get_contents($path);
        $this->assertStringContainsString('trait ManagerOrder', $src);
        $this->assertStringContainsString('function created()', $src);
        $this->assertStringContainsString('tryReuseRecentPendingTask', $src);
        $this->assertStringContainsString('normalizedOrderAttemptId', $src);

        $this->assertTrue(class_exists(\iEXPackages\Order\Order::class));
        $this->assertTrue(method_exists(\iEXPackages\Order\Order::class, 'created'));
        $this->assertTrue(method_exists(\iEXPackages\Order\Order::class, 'request'));
    }

    public function test_order_pay_controller_fails_closed_when_binding_missing(): void
    {
        $root = dirname(__DIR__, 3);
        $path = $root.'/packages/ExchangerClient/Http/Controller/Operations/OrderPayController.php';
        $this->assertFileExists($path);
        $src = (string) file_get_contents($path);
        $this->assertStringContainsString('ManagerOrder.php', $src);
        $this->assertStringContainsString('SERVICE_UNAVAILABLE', $src);
        $this->assertStringContainsString('DIRECTION_UNAVAILABLE', $src);
    }
}
