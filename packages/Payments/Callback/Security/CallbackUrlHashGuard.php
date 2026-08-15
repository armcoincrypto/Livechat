<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Callback\Security;

/**
 * URL-token (merchant.security_hash) authentication for inbound callbacks.
 *
 * Providers whose protocol uses a different signature (HMAC headers, etc.)
 * must set callback.url_hash_required = false in gateway config.php.
 */
final class CallbackUrlHashGuard
{
    /**
     * Default: enabled callbacks require a configured merchant URL hash.
     */
    public static function urlHashRequired(array $callbackConfig): bool
    {
        if (array_key_exists('url_hash_required', $callbackConfig)) {
            return (bool) $callbackConfig['url_hash_required'];
        }

        return (bool) ($callbackConfig['enabled'] ?? false);
    }

    /**
     * @return array{ok:bool, event:?string, http:?int, body:?string}
     */
    public static function verify(string $expectedHash, string $securityHashFromUrl, bool $required): array
    {
        $expected = trim($expectedHash);
        $provided = trim($securityHashFromUrl);

        if ($expected === '') {
            if ($required) {
                return [
                    'ok' => false,
                    'event' => 'signature_missing',
                    'http' => 403,
                    'body' => 'Invalid hash',
                ];
            }

            return ['ok' => true, 'event' => null, 'http' => null, 'body' => null];
        }

        if ($provided === '') {
            return [
                'ok' => false,
                'event' => 'signature_missing',
                'http' => 403,
                'body' => 'Invalid hash',
            ];
        }

        if (!hash_equals($expected, $provided)) {
            return [
                'ok' => false,
                'event' => 'signature_invalid',
                'http' => 403,
                'body' => 'Invalid hash',
            ];
        }

        return ['ok' => true, 'event' => null, 'http' => null, 'body' => null];
    }
}
