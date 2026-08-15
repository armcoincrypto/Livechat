<?php

declare(strict_types=1);

namespace Tests\Unit\Gateways\Kobbopay;

use App\Gateways\Crypto\Kobbopay\Services\KobbopayWebhookSignatureVerifier;
use PHPUnit\Framework\TestCase;

final class KobbopayWebhookSignatureVerifierTest extends TestCase
{
    private KobbopayWebhookSignatureVerifier $verifier;
    private string $secret = 'test-webhook-secret-not-real';

    protected function setUp(): void
    {
        parent::setUp();
        $this->verifier = new KobbopayWebhookSignatureVerifier(300);
    }

    public function test_valid_signature_accepted(): void
    {
        $body = '{"event":"payment.confirmed","paymentId":"pay_1","orderId":"4041"}';
        $ts = (string) time();
        $sig = $this->verifier->sign($this->secret, $ts, $body);

        $result = $this->verifier->verify($body, [
            'X-Kobbopay-Event-Id' => 'payment:pay_1:payment.confirmed',
            'X-Kobbopay-Timestamp' => $ts,
            'X-Kobbopay-Signature' => $sig,
            'X-Kobbopay-Delivery-Id' => '42',
        ], $this->secret);

        $this->assertTrue($result['ok']);
        $this->assertSame('payment:pay_1:payment.confirmed', $result['event_id']);
        $this->assertSame('42', $result['delivery_id']);
    }

    public function test_invalid_signature_rejected(): void
    {
        $body = '{"event":"payment.confirmed"}';
        $ts = (string) time();

        $result = $this->verifier->verify($body, [
            'X-Kobbopay-Event-Id' => 'evt-1',
            'X-Kobbopay-Timestamp' => $ts,
            'X-Kobbopay-Signature' => str_repeat('a', 64),
        ], $this->secret);

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_signature', $result['error']);
    }

    public function test_empty_signature_rejected(): void
    {
        $result = $this->verifier->verify('{}', [
            'X-Kobbopay-Event-Id' => 'evt-1',
            'X-Kobbopay-Timestamp' => (string) time(),
            'X-Kobbopay-Signature' => '',
        ], $this->secret);

        $this->assertFalse($result['ok']);
        $this->assertSame('missing_headers', $result['error']);
    }

    public function test_missing_signature_rejected(): void
    {
        $result = $this->verifier->verify('{}', [
            'X-Kobbopay-Event-Id' => 'evt-1',
            'X-Kobbopay-Timestamp' => (string) time(),
        ], $this->secret);

        $this->assertFalse($result['ok']);
        $this->assertSame('missing_headers', $result['error']);
    }

    public function test_missing_timestamp_rejected(): void
    {
        $result = $this->verifier->verify('{}', [
            'X-Kobbopay-Event-Id' => 'evt-1',
            'X-Kobbopay-Signature' => str_repeat('b', 64),
        ], $this->secret);

        $this->assertFalse($result['ok']);
        $this->assertSame('missing_headers', $result['error']);
    }

    public function test_malformed_timestamp_rejected(): void
    {
        $result = $this->verifier->verify('{}', [
            'X-Kobbopay-Event-Id' => 'evt-1',
            'X-Kobbopay-Timestamp' => 'not-a-unix-ts',
            'X-Kobbopay-Signature' => str_repeat('c', 64),
        ], $this->secret);

        $this->assertFalse($result['ok']);
        $this->assertSame('malformed_timestamp', $result['error']);
    }

    public function test_stale_timestamp_rejected(): void
    {
        $body = '{}';
        $ts = (string) (time() - 3600);
        $sig = $this->verifier->sign($this->secret, $ts, $body);

        $result = $this->verifier->verify($body, [
            'X-Kobbopay-Event-Id' => 'evt-stale',
            'X-Kobbopay-Timestamp' => $ts,
            'X-Kobbopay-Signature' => $sig,
        ], $this->secret);

        $this->assertFalse($result['ok']);
        $this->assertSame('stale_timestamp', $result['error']);
    }

    public function test_raw_body_mutation_invalidates_signature(): void
    {
        $body = '{"event":"payment.confirmed","paymentId":"1"}';
        $ts = (string) time();
        $sig = $this->verifier->sign($this->secret, $ts, $body);

        $result = $this->verifier->verify($body . ' ', [
            'X-Kobbopay-Event-Id' => 'evt-mut',
            'X-Kobbopay-Timestamp' => $ts,
            'X-Kobbopay-Signature' => $sig,
        ], $this->secret);

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_signature', $result['error']);
    }

    public function test_uses_hash_equals_path_via_valid_compare(): void
    {
        // Behavioral proof: only exact HMAC matches; wrong secret fails.
        $body = '{"ok":true}';
        $ts = (string) time();
        $sig = $this->verifier->sign($this->secret, $ts, $body);

        $bad = $this->verifier->verify($body, [
            'X-Kobbopay-Event-Id' => 'evt-hash',
            'X-Kobbopay-Timestamp' => $ts,
            'X-Kobbopay-Signature' => $sig,
        ], 'wrong-secret');

        $this->assertFalse($bad['ok']);
        $this->assertSame('invalid_signature', $bad['error']);
    }
}
