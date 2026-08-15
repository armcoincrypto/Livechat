<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Kobbopay\Services;

/**
 * Inbound Kobbopay P28 webhook signature verification.
 *
 * Signed message: timestamp + "." + rawBody
 * Algorithm: HMAC-SHA256 → hex digest
 * Headers: X-Kobbopay-Timestamp, X-Kobbopay-Signature, X-Kobbopay-Event-Id, X-Kobbopay-Delivery-Id
 */
final class KobbopayWebhookSignatureVerifier
{
    public const HEADER_EVENT_ID = 'X-Kobbopay-Event-Id';
    public const HEADER_TIMESTAMP = 'X-Kobbopay-Timestamp';
    public const HEADER_SIGNATURE = 'X-Kobbopay-Signature';
    public const HEADER_DELIVERY_ID = 'X-Kobbopay-Delivery-Id';

    private readonly int $toleranceSeconds;

    public function __construct(?int $toleranceSeconds = null)
    {
        $resolved = $toleranceSeconds ?? (int) env('KOBBOPAY_WEBHOOK_TOLERANCE_SECONDS', 300);
        $this->toleranceSeconds = $resolved > 0 ? $resolved : 300;
    }

    /**
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   event_id?: string,
     *   delivery_id?: string|null,
     *   timestamp?: int
     * }
     */
    public function verify(string $rawBody, array $headers, string $secret): array
    {
        if ($secret === '') {
            return ['ok' => false, 'error' => 'missing_secret'];
        }

        $eventId = $this->header($headers, self::HEADER_EVENT_ID);
        $timestampRaw = $this->header($headers, self::HEADER_TIMESTAMP);
        $signature = $this->header($headers, self::HEADER_SIGNATURE);
        $deliveryId = $this->header($headers, self::HEADER_DELIVERY_ID);

        if ($eventId === '' || $timestampRaw === '' || $signature === '') {
            return ['ok' => false, 'error' => 'missing_headers'];
        }

        if (!ctype_digit($timestampRaw)) {
            return ['ok' => false, 'error' => 'malformed_timestamp'];
        }

        $timestamp = (int) $timestampRaw;
        $now = time();
        if (abs($now - $timestamp) > $this->toleranceSeconds) {
            return ['ok' => false, 'error' => 'stale_timestamp'];
        }

        $expected = $this->sign($secret, $timestampRaw, $rawBody);
        // Normalize hex case for constant-time compare.
        if (!hash_equals($expected, strtolower($signature))) {
            return [
                'ok' => false,
                'error' => 'invalid_signature',
                'event_id' => $eventId,
                'delivery_id' => $deliveryId !== '' ? $deliveryId : null,
                'timestamp' => $timestamp,
            ];
        }

        return [
            'ok' => true,
            'event_id' => $eventId,
            'delivery_id' => $deliveryId !== '' ? $deliveryId : null,
            'timestamp' => $timestamp,
        ];
    }

    public function sign(string $secret, string $timestamp, string $rawBody): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
    }

    /**
     * @param array<string, mixed> $headers
     */
    private function header(array $headers, string $name): string
    {
        $lower = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $lower) {
                continue;
            }
            if (is_array($value)) {
                $value = $value[0] ?? '';
            }
            return trim((string) $value);
        }

        return '';
    }
}
