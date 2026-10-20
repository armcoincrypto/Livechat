<?php

declare(strict_types=1);

namespace App\Services\Auth\OAuth;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Session-bound OAuth transaction state (single-use, expiring).
 * Never stores authorization codes or tokens.
 */
final class OAuthStateStore
{
    private const SESSION_KEY = 'exs_oauth_tx';
    private const TTL_SECONDS = 600;

    /**
     * @param array{
     *   provider: string,
     *   intent: 'login'|'link',
     *   locale: string,
     *   return_to: ?string,
     *   link_user_id: ?int,
     *   code_verifier: string,
     *   nonce: string
     * } $payload
     */
    public function begin(array $payload): string
    {
        $state = Str::random(48);
        Session::put(self::SESSION_KEY, [
            'state' => $state,
            'provider' => OAuthProviders::normalize($payload['provider']),
            'intent' => $payload['intent'],
            'locale' => $payload['locale'],
            'return_to' => $payload['return_to'],
            'link_user_id' => $payload['link_user_id'],
            'code_verifier' => $payload['code_verifier'],
            'nonce' => $payload['nonce'],
            'expires_at' => time() + self::TTL_SECONDS,
            'used' => false,
        ]);

        return $state;
    }

    /**
     * Consume and validate state (single use).
     *
     * @return array{
     *   provider: string,
     *   intent: string,
     *   locale: string,
     *   return_to: ?string,
     *   link_user_id: ?int,
     *   code_verifier: string,
     *   nonce: string
     * }
     */
    public function consume(string $provider, string $state): array
    {
        $tx = Session::get(self::SESSION_KEY);
        Session::forget(self::SESSION_KEY);

        if (!is_array($tx)) {
            throw new RuntimeException('OAUTH_STATE_MISSING');
        }

        if (!empty($tx['used'])) {
            throw new RuntimeException('OAUTH_STATE_REUSED');
        }

        if (!hash_equals((string) ($tx['state'] ?? ''), $state)) {
            throw new RuntimeException('OAUTH_STATE_MISMATCH');
        }

        if (OAuthProviders::normalize((string) ($tx['provider'] ?? '')) !== OAuthProviders::normalize($provider)) {
            throw new RuntimeException('OAUTH_STATE_PROVIDER_MISMATCH');
        }

        if ((int) ($tx['expires_at'] ?? 0) < time()) {
            throw new RuntimeException('OAUTH_STATE_EXPIRED');
        }

        return [
            'provider' => OAuthProviders::normalize((string) $tx['provider']),
            'intent' => (string) $tx['intent'],
            'locale' => (string) ($tx['locale'] ?? 'en'),
            'return_to' => isset($tx['return_to']) ? (is_string($tx['return_to']) ? $tx['return_to'] : null) : null,
            'link_user_id' => isset($tx['link_user_id']) && $tx['link_user_id'] !== null ? (int) $tx['link_user_id'] : null,
            'code_verifier' => (string) $tx['code_verifier'],
            'nonce' => (string) $tx['nonce'],
        ];
    }

    public static function createCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function codeChallengeS256(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
