<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Services\Orders\PaymentDestinationRouter;
use App\Services\Orders\WaitingDepositHealthClassifier as C;
use Tests\TestCase;

final class WaitingDepositHealthClassifierTest extends TestCase
{
    public function test_new_payable_kobbopay_without_dest_fails(): void
    {
        $kind = C::classify([
            'has_destination' => false,
            'has_source' => true,
            'owner' => PaymentDestinationRouter::OWNER_KOBBOPAY,
            'public_id' => '1999999999999',
            'email' => 'customer@example.com',
        ]);
        $this->assertSame(C::KIND_NEW_PAYABLE_ORDER_WITHOUT_DESTINATION, $kind);
        $this->assertTrue(C::isActionableFail($kind));
    }

    public function test_known_usdc_blocked_does_not_fail(): void
    {
        $kind = C::classify([
            'has_destination' => false,
            'has_source' => false,
            'owner' => PaymentDestinationRouter::OWNER_EXSWAPING_REQUISITE,
            'public_id' => '1786828013012',
            'email' => 'dcdssscs@gmail.com',
        ]);
        $this->assertSame(C::KIND_HISTORICAL_BLOCKED, $kind);
        $this->assertFalse(C::isActionableFail($kind));
    }

    public function test_zelle_verification_pending_does_not_fail(): void
    {
        $kind = C::classify([
            'has_destination' => false,
            'has_source' => true,
            'owner' => PaymentDestinationRouter::OWNER_ZELLE_VERIFICATION,
            'public_id' => '1786831162680',
            'email' => 'customer@example.com',
        ]);
        $this->assertSame(C::KIND_ZELLE_VERIFICATION_PENDING, $kind);
        $this->assertFalse(C::isActionableFail($kind));
    }

    public function test_canary_email_is_test_artifact(): void
    {
        $kind = C::classify([
            'has_destination' => false,
            'has_source' => true,
            'owner' => PaymentDestinationRouter::OWNER_KOBBOPAY,
            'public_id' => '1787046464545',
            'email' => 'browser-e2e-canary@qa.exswaping.com',
        ]);
        $this->assertSame(C::KIND_TEST_ARTIFACT_UNPAID, $kind);
        $this->assertFalse(C::isActionableFail($kind));
    }

    public function test_intentionally_unavailable_requisite_rail_does_not_fail(): void
    {
        $kind = C::classify([
            'has_destination' => false,
            'has_source' => false,
            'owner' => PaymentDestinationRouter::OWNER_EXSWAPING_REQUISITE,
            'public_id' => '1888888888888',
            'email' => 'someone@example.com',
        ]);
        $this->assertSame(C::KIND_INTENTIONALLY_UNAVAILABLE, $kind);
        $this->assertFalse(C::isActionableFail($kind));
    }

    public function test_assigned_destination_is_ok(): void
    {
        $this->assertSame(C::KIND_HAS_DESTINATION, C::classify([
            'has_destination' => true,
            'has_source' => true,
            'owner' => PaymentDestinationRouter::OWNER_KOBBOPAY,
            'public_id' => '1',
            'email' => 'a@b.c',
        ]));
    }
}
