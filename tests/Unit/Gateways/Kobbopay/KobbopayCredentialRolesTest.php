<?php

declare(strict_types=1);

namespace Tests\Unit\Gateways\Kobbopay;

use App\Gateways\Crypto\Kobbopay\Services\KobbopayWebhookSignatureVerifier;
use App\Gateways\Crypto\Kobbopay\Services\SecureSignatureService;
use PHPUnit\Framework\TestCase;

/**
 * Proves outbound vs inbound signing roles stay separated (no duplicate crypto impl).
 */
final class KobbopayCredentialRolesTest extends TestCase
{
    public function test_outbound_api_signature_uses_private_key_hmac_sha512(): void
    {
        $privateKey = str_repeat('p', 64);
        $webhookSecret = 'webhook-secret-not-for-outbound';
        $body = ['amount' => '10', 'currency' => 'USDT'];
        $ts = 1700000000;

        $svc = new SecureSignatureService($privateKey, 'sha512');
        $svc->setTimestamp($ts);
        $sig = $svc->generateSignature($body);

        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $expected = hash_hmac('sha512', $ts . $json, $privateKey);
        $wrongRole = hash_hmac('sha512', $ts . $json, $webhookSecret);

        $this->assertSame($expected, $sig);
        $this->assertFalse(hash_equals($sig, $wrongRole));
        $this->assertTrue($svc->verifySignature($sig, $body, $ts));
    }

    public function test_inbound_webhook_verifier_uses_webhook_secret_hmac_sha256(): void
    {
        $webhookSecret = 'webhook-secret-for-inbound-only';
        $privateKey = str_repeat('p', 64);
        $body = '{"event":"payment.webhook_test","paymentId":"x"}';
        $ts = (string) time();

        $verifier = new KobbopayWebhookSignatureVerifier(300);
        $sig = $verifier->sign($webhookSecret, $ts, $body);

        $ok = $verifier->verify($body, [
            'X-Kobbopay-Event-Id' => 'evt-role-1',
            'X-Kobbopay-Timestamp' => $ts,
            'X-Kobbopay-Signature' => $sig,
        ], $webhookSecret);
        $this->assertTrue($ok['ok']);

        $bad = $verifier->verify($body, [
            'X-Kobbopay-Event-Id' => 'evt-role-1',
            'X-Kobbopay-Timestamp' => $ts,
            'X-Kobbopay-Signature' => $sig,
        ], $privateKey);
        $this->assertFalse($bad['ok']);
    }
}
