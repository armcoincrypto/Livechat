<?php

declare(strict_types=1);

namespace Tests\Unit\Gateways\Kobbopay;

use Tests\TestCase;

/**
 * Invoice create retries must reuse the same client_transaction_id (task id).
 * No HTTP Idempotency-Key is sent; uniqueness is the Kobbopay client_transaction_id field.
 */
final class KobbopayPurchaseIdempotencyContractTest extends TestCase
{
    public function test_purchase_payload_and_retry_use_stable_client_transaction_id(): void
    {
        $src = (string) file_get_contents(app_path('Gateways/Crypto/Kobbopay/Messages/PurchaseRequest.php'));

        $this->assertStringContainsString("'token' => (string) \$this->getMerchantNetworkCode()", $src);
        $this->assertStringContainsString("'client_transaction_id' => (string) \$this->getTransactionId()", $src);
        $this->assertStringContainsString("'/api/crypto/invoice/create'", $src);
        $this->assertStringContainsString('$maxAttempts = 3', $src);
        $this->assertStringContainsString("\$this->sendRequest('post', '/api/crypto/invoice/create', \$data", $src);
        $this->assertStringNotContainsString('Idempotency-Key', $src);
        $this->assertStringNotContainsString('uniqid', $src);
        $this->assertStringNotContainsString('Str::uuid', $src);
    }
}
