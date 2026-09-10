<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Services\Orders\InboundPaymentDestinationGuard;
use App\Services\Orders\PaymentDestinationFailClosedGuard;
use App\Services\Orders\PaymentDestinationRouter;
use PHPUnit\Framework\TestCase;

/**
 * Fail-closed: destination-required rails must not remain customer-payable without a dest.
 * No real Kobbopay. Decision tests are pure; wiring is asserted in source.
 */
final class PaymentDestinationFailClosedGuardTest extends TestCase
{
    public function test_success_keeps_payable_when_destination_present(): void
    {
        $d = PaymentDestinationFailClosedGuard::decide(
            PaymentDestinationRouter::OWNER_KOBBOPAY,
            true,
        );
        $this->assertFalse($d['reject']);
        $this->assertTrue($d['restore_pending']);
    }

    public function test_provider_exception_is_treated_as_missing_destination(): void
    {
        $d = PaymentDestinationFailClosedGuard::decide(
            PaymentDestinationRouter::OWNER_KOBBOPAY,
            false,
        );
        $this->assertTrue($d['reject']);
        $this->assertFalse($d['restore_pending']);
        $this->assertTrue(PaymentDestinationFailClosedGuard::destinationRequired(
            PaymentDestinationRouter::OWNER_KOBBOPAY
        ));
    }

    public function test_empty_null_destination_cannot_remain_payable(): void
    {
        $this->assertTrue(PaymentDestinationFailClosedGuard::mustNotRemainPayable(
            PaymentDestinationRouter::OWNER_KOBBOPAY,
            false,
        ));
        $this->assertFalse(PaymentDestinationFailClosedGuard::mustNotRemainPayable(
            PaymentDestinationRouter::OWNER_KOBBOPAY,
            true,
        ));
    }

    public function test_persistence_failure_cannot_remain_payable(): void
    {
        $this->assertSame(
            ['reject' => true, 'restore_pending' => false],
            PaymentDestinationFailClosedGuard::decide(
                PaymentDestinationRouter::OWNER_KOBBOPAY,
                false,
            )
        );
    }

    public function test_non_required_manual_and_zelle_rails_are_not_this_invariant(): void
    {
        $this->assertFalse(PaymentDestinationFailClosedGuard::destinationRequired(
            PaymentDestinationRouter::OWNER_EXSWAPING_REQUISITE
        ));
        $this->assertFalse(PaymentDestinationFailClosedGuard::destinationRequired(
            PaymentDestinationRouter::OWNER_ZELLE_VERIFICATION
        ));
        $this->assertFalse(PaymentDestinationFailClosedGuard::mustNotRemainPayable(
            PaymentDestinationRouter::OWNER_EXSWAPING_REQUISITE,
            false,
        ));
        $this->assertFalse(PaymentDestinationFailClosedGuard::mustNotRemainPayable(
            PaymentDestinationRouter::OWNER_ZELLE_VERIFICATION,
            false,
        ));
    }

    public function test_create_path_enforces_db_snapshot_not_invoice_mode(): void
    {
        $manager = (string) file_get_contents(dirname(__DIR__, 3).'/packages/Order/Bindings/ManagerOrder.php');
        $this->assertStringContainsString('PaymentDestinationFailClosedGuard', $manager);
        $this->assertStringContainsString('enforceAfterCreate', $manager);
        $this->assertStringContainsString('taskHasAssignedDestination', $manager);
        $this->assertStringContainsString('taskMatchesReuseContract', $manager);
    }

    public function test_idempotent_reuse_skips_destination_required_orders_without_dest(): void
    {
        $manager = (string) file_get_contents(dirname(__DIR__, 3).'/packages/Order/Bindings/ManagerOrder.php');
        $this->assertMatchesRegularExpression(
            '/function taskMatchesReuseContract[\s\S]+mustNotRemainPayable/',
            $manager
        );
        $this->assertMatchesRegularExpression(
            '/function taskMatchesReuseContract[\s\S]+taskHasAssignedDestination/',
            $manager
        );
    }

    public function test_kobbopay_unbinds_merchant_when_mtd_missing_after_issue(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/packages/Order/Invoices/Concerns/HasMerchant.php');
        $this->assertStringContainsString('clearMerchantBindingIfDestinationMissing', $src);
        $this->assertStringContainsString('payment_destination_merchant_unbound', $src);
    }

    public function test_fail_closed_log_has_no_pii_or_secrets(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Orders/PaymentDestinationFailClosedGuard.php');
        $this->assertStringContainsString("Log::warning('payment_destination_fail_closed'", $src);
        $this->assertStringContainsString("'public_id'", $src);
        $this->assertStringContainsString("'merchant_id'", $src);
        $this->assertStringContainsString("'provider'", $src);
        $this->assertStringContainsString("'failure_class'", $src);
        $this->assertStringNotContainsString("'email'", $src);
        $this->assertStringNotContainsString('wallet_number', $src);
        $this->assertStringNotContainsString('password', $src);
        $this->assertStringNotContainsString('secret', $src);
    }

    public function test_waiting_deposit_classifier_still_flags_new_payable_without_dest(): void
    {
        $this->assertTrue(InboundPaymentDestinationGuard::ERROR_PAYMENT_DESTINATION_UNAVAILABLE === 'PAYMENT_DESTINATION_UNAVAILABLE');
        $classifier = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Orders/WaitingDepositHealthClassifier.php');
        $this->assertStringContainsString('KIND_NEW_PAYABLE_ORDER_WITHOUT_DESTINATION', $classifier);
    }

    public function test_error_payload_stays_customer_safe(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Orders/PaymentDestinationFailClosedGuard.php');
        $this->assertStringContainsString('ERROR_PAYMENT_DESTINATION_UNAVAILABLE', $src);
        $this->assertStringContainsString('Приём средств по этому направлению временно недоступен', $src);
    }
}
