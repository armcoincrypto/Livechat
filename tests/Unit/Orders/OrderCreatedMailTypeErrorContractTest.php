<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use PHPUnit\Framework\TestCase;

/**
 * Failed job 14447: htmlspecialchars received currency1.field_name_from = [].
 * Keep fail-closed mail timing and queued-job recheck.
 */
final class OrderCreatedMailTypeErrorContractTest extends TestCase
{
    public function test_created_mail_view_does_not_echo_translatable_field_name_from(): void
    {
        $view = (string) file_get_contents(
            dirname(__DIR__, 3).'/packages/SmartMailer/Resources/Views/orders/order_created.blade.php'
        );
        $this->assertStringNotContainsString(
            '$order->direction_exchange->currency1->field_name_from',
            $view,
        );
        $this->assertStringNotContainsString(
            '$order->direction_exchange->currency2->field_name_to',
            $view,
        );
        $this->assertStringContainsString('$fieldNameFrom', $view);
        $this->assertStringContainsString('$fieldNameTo', $view);
        $this->assertStringContainsString('$sendPaymentName', $view);
    }

    public function test_mailable_normalizes_display_fields_before_markdown(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3).'/packages/SmartMailer/Dispatches/Orders/OrderCreatedMail.php'
        );
        $this->assertStringContainsString('OrderMailDisplayText::scalar', $src);
        $this->assertStringContainsString("'fieldNameFrom'", $src);
        $this->assertStringContainsString('scalarTasksFields', $src);
        $this->assertStringNotContainsString(
            '$inCurrency->account_number_field ?: __',
            $src,
        );
    }

    public function test_fail_closed_mail_timing_is_preserved(): void
    {
        $created = $this->methodSource($this->managerOrderSource(), 'created');
        $this->assertLessThan(
            strpos($created, 'dispatchOrderCreatedMailIfEligible') ?: PHP_INT_MAX,
            strpos($created, 'abandonIfPaymentDestinationMissing') ?: PHP_INT_MAX,
        );
        $abandonedReturnPos = strpos($created, 'if ($abandoned !== null)');
        $dispatchPos = strpos($created, 'dispatchOrderCreatedMailIfEligible');
        $this->assertNotFalse($abandonedReturnPos);
        $this->assertNotFalse($dispatchPos);
        $this->assertLessThan($dispatchPos, $abandonedReturnPos);
    }

    public function test_queued_job_still_rechecks_order_created_condition(): void
    {
        $job = (string) file_get_contents(
            dirname(__DIR__, 3).'/packages/SmartMailer/DispatchJobs/Orders/OrderCreatedMailJob.php'
        );
        $this->assertStringContainsString('OrderCreatedCondition', $job);
        $this->assertStringContainsString('shouldSend()', $job);
        $this->assertStringContainsString('refresh()', $job);
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
