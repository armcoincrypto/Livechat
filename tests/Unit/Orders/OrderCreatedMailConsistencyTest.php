<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Services\Orders\PaymentDestinationRouter;
use iEXPackages\SmartMailer\Conditions\OrderCreatedCondition;
use PHPUnit\Framework\TestCase;

/**
 * Customer must not receive a normal OrderCreatedMail for an order that
 * never became usably payable (destination-required Kobbopay rails).
 * No real SMTP. No real Kobbopay.
 */
final class OrderCreatedMailConsistencyTest extends TestCase
{
    public function test_kobbopay_success_allows_created_mail_when_destination_present(): void
    {
        $this->assertTrue(OrderCreatedCondition::destinationAllowsCreatedMail(
            PaymentDestinationRouter::OWNER_KOBBOPAY,
            true,
        ));
    }

    public function test_kobbopay_provider_exception_or_missing_destination_blocks_created_mail(): void
    {
        $this->assertFalse(OrderCreatedCondition::destinationAllowsCreatedMail(
            PaymentDestinationRouter::OWNER_KOBBOPAY,
            false,
        ));
    }

    public function test_empty_mtd_equivalent_blocks_created_mail(): void
    {
        $this->assertFalse(OrderCreatedCondition::destinationAllowsCreatedMail(
            PaymentDestinationRouter::OWNER_KOBBOPAY,
            false,
        ));
    }

    public function test_non_required_manual_and_zelle_rails_preserve_mail_eligibility(): void
    {
        $this->assertTrue(OrderCreatedCondition::destinationAllowsCreatedMail(
            PaymentDestinationRouter::OWNER_EXSWAPING_REQUISITE,
            false,
        ));
        $this->assertTrue(OrderCreatedCondition::destinationAllowsCreatedMail(
            PaymentDestinationRouter::OWNER_ZELLE_VERIFICATION,
            false,
        ));
        $this->assertTrue(OrderCreatedCondition::destinationAllowsCreatedMail(
            PaymentDestinationRouter::OWNER_UNSUPPORTED,
            false,
        ));
    }

    public function test_create_path_dispatches_mail_only_after_fail_closed(): void
    {
        $manager = $this->managerOrderSource();
        $created = $this->methodSource($manager, 'created');
        $addition = $this->methodSource($manager, 'additionInfo');
        $dispatch = $this->methodSource($manager, 'dispatchOrderCreatedMailIfEligible');

        $this->assertStringContainsString('abandonIfPaymentDestinationMissing', $created);
        $this->assertStringContainsString('dispatchOrderCreatedMailIfEligible', $created);
        $this->assertLessThan(
            strpos($created, 'dispatchOrderCreatedMailIfEligible') ?: PHP_INT_MAX,
            strpos($created, 'abandonIfPaymentDestinationMissing') ?: PHP_INT_MAX,
            'OrderCreatedMail must wait until fail-closed has a final payable state',
        );
        $abandonedReturnPos = strpos($created, 'if ($abandoned !== null)');
        $dispatchPos = strpos($created, 'dispatchOrderCreatedMailIfEligible');
        $this->assertNotFalse($abandonedReturnPos);
        $this->assertNotFalse($dispatchPos);
        $this->assertLessThan($dispatchPos, $abandonedReturnPos);

        $this->assertStringNotContainsString('order_created_job', $addition);
        $this->assertStringContainsString("sendable: 'order_created_job'", $dispatch);
        $this->assertStringContainsString("make('order_created'", $dispatch);
        $this->assertSame(1, substr_count($manager, "sendable: 'order_created_job'"));
    }

    public function test_idempotent_reuse_does_not_dispatch_created_mail(): void
    {
        $created = $this->methodSource($this->managerOrderSource(), 'created');
        $reuseReturn = strpos($created, "'idempotent_reuse'");
        $dispatchPos = strpos($created, 'dispatchOrderCreatedMailIfEligible');
        $this->assertNotFalse($reuseReturn);
        $this->assertNotFalse($dispatchPos);
        $this->assertLessThan(
            $dispatchPos,
            $reuseReturn,
            'reuse must return before the single create-path mail dispatch',
        );
    }

    public function test_queued_job_rechecks_eligibility_before_send(): void
    {
        $job = (string) file_get_contents(dirname(__DIR__, 3).'/packages/SmartMailer/DispatchJobs/Orders/OrderCreatedMailJob.php');
        $this->assertStringContainsString('OrderCreatedCondition', $job);
        $this->assertStringContainsString('shouldSend()', $job);
        $this->assertStringContainsString('order_created_mail_skipped', $job);
        $this->assertStringContainsString('refresh()', $job);
        $this->assertStringNotContainsString('$this->order->email', explode('shouldSend()', $job)[0] ?? '');
        $this->assertStringNotContainsString("'email'", $job);
        $this->assertStringNotContainsString('wallet_number', $job);
        $this->assertStringNotContainsString('password', $job);
    }

    public function test_condition_reuses_fail_closed_destination_required_rule(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/packages/SmartMailer/Conditions/OrderCreatedCondition.php');
        $this->assertStringContainsString('destinationAllowsCreatedMail', $src);
        $this->assertStringContainsString('PaymentDestinationFailClosedGuard', $src);
        $this->assertStringContainsString('taskHasAssignedDestination', $src);
        $this->assertStringContainsString('mustNotRemainPayable', $src);
    }

    public function test_single_order_created_mail_owner_is_unchanged(): void
    {
        $config = (string) file_get_contents(dirname(__DIR__, 3).'/config/smart-mailer.php');
        $this->assertStringContainsString("order_created_job", $config);
        $this->assertSame(
            1,
            substr_count($config, 'OrderCreatedMailJob::class'),
        );
        $this->assertSame(
            1,
            substr_count($config, 'OrderCreatedMail::class'),
        );
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
