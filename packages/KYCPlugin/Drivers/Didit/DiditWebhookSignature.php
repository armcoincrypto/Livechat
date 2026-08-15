<?php

declare(strict_types=1);

namespace iEXPackages\KYCPlugin\Drivers\Didit;

/**
 * Didit webhook HMAC verification (prefer X-Signature-V2).
 *
 * Canonical V2 matches Didit docs: recursive key sort, whole floats shortened to ints,
 * compact JSON with Unicode preserved (JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).
 *
 * @see https://docs.didit.me/integration/webhooks
 */
final class DiditWebhookSignature
{
    /**
     * @param array<string, mixed>|null $decodedJson
     */
    public static function isValid(
        string $secret,
        string $rawBody,
        ?array $decodedJson,
        ?string $signatureV2,
        ?string $signatureRaw,
        ?string $signatureSimple,
        ?string $timestampHeader = null,
    ): bool {
        $secret = trim($secret);
        if ($secret === '') {
            return false;
        }

        if (is_string($signatureV2) && $signatureV2 !== '' && is_array($decodedJson)) {
            $canonical = self::canonicalJson($decodedJson);
            if (self::matchesHmac($secret, $canonical, $signatureV2)) {
                return true;
            }
        }

        if (is_string($signatureRaw) && $signatureRaw !== '') {
            // Exact transmitted body bytes.
            if (self::matchesHmac($secret, $rawBody, $signatureRaw)) {
                return true;
            }
            // Didit also documents X-Signature over ASCII-escaped sorted JSON.
            if (is_array($decodedJson)) {
                $asciiCanonical = self::canonicalJsonAscii($decodedJson);
                if (self::matchesHmac($secret, $asciiCanonical, $signatureRaw)) {
                    return true;
                }
            }
        }

        if (is_string($signatureSimple) && $signatureSimple !== '' && is_array($decodedJson)) {
            $timestamp = (string) ($decodedJson['timestamp'] ?? $decodedJson['created_at'] ?? '');
            if ($timestamp === '' && is_string($timestampHeader) && $timestampHeader !== '') {
                $timestamp = $timestampHeader;
            }
            $sessionId = (string) ($decodedJson['session_id'] ?? '');
            $status = (string) ($decodedJson['status'] ?? '');
            $webhookType = (string) ($decodedJson['webhook_type'] ?? '');
            if ($timestamp !== '' && $sessionId !== '' && $status !== '' && $webhookType !== '') {
                $simplePayload = $timestamp . ':' . $sessionId . ':' . $status . ':' . $webhookType;
                if (self::matchesHmac($secret, $simplePayload, $signatureSimple)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function canonicalJson(array $data): string
    {
        $normalized = self::sortKeysRecursive(self::shortenFloats($data));
        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '';
    }

    /**
     * ASCII-escaped compact JSON (ensure_ascii=True equivalent) for legacy X-Signature.
     *
     * @param array<string, mixed> $data
     */
    public static function canonicalJsonAscii(array $data): string
    {
        $normalized = self::sortKeysRecursive(self::shortenFloats($data));
        $encoded = json_encode($normalized, JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '';
    }

    private static function matchesHmac(string $secret, string $payload, string $signature): bool
    {
        $signature = trim($signature);
        if (str_starts_with(strtolower($signature), 'sha256=')) {
            $signature = substr($signature, 7);
        }
        $digest = hash_hmac('sha256', $payload, $secret);

        return hash_equals($digest, strtolower($signature)) || hash_equals($digest, $signature);
    }

    private static function shortenFloats(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = self::shortenFloats($item);
            }

            return $out;
        }

        if (is_float($value) && floor($value) == $value) {
            return (int) $value;
        }

        return $value;
    }

    private static function sortKeysRecursive(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (self::isList($value)) {
            $out = [];
            foreach ($value as $item) {
                $out[] = self::sortKeysRecursive($item);
            }

            return $out;
        }

        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = self::sortKeysRecursive($value[$key]);
        }

        return $out;
    }

    /**
     * @param array<mixed> $value
     */
    private static function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
