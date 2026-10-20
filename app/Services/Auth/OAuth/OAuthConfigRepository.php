<?php

declare(strict_types=1);

namespace App\Services\Auth\OAuth;

use App\Models\SocialAuthSystem;
use RuntimeException;

/**
 * Admin panel (`social_auth_system`) is the source of truth for OAuth client credentials.
 *
 * - client_id / client_secret are NEVER read from environment variables.
 * - Optional EXS_OAUTH_{PROVIDER}_REDIRECT_URI may override the callback URL only.
 * - Reads are live DB queries (no cache) so admin status/credential edits apply immediately.
 * - Secrets must never be logged or returned by operator diagnostics.
 */
final class OAuthConfigRepository
{
    /**
     * @return array{client_id: string, client_secret: string, alias: string, name: string}
     */
    public function getEnabledProvider(string $provider): array
    {
        $alias = OAuthProviders::normalize($provider);

        if (!OAuthProviders::isSupported($alias)) {
            throw new RuntimeException('OAUTH_PROVIDER_UNSUPPORTED');
        }

        // Refuse credential env overrides so admin-managed secrets remain authoritative.
        $this->assertNoCredentialEnvOverrides($alias);

        /** @var SocialAuthSystem|null $row */
        $row = SocialAuthSystem::query()
            ->where('alias', $alias)
            ->where('status', 1)
            ->first();

        if (!$row) {
            // Distinguish disabled vs missing alias for operators (no secrets).
            $any = SocialAuthSystem::query()->where('alias', $alias)->exists();
            throw new RuntimeException($any ? 'OAUTH_PROVIDER_DISABLED' : 'OAUTH_PROVIDER_MISSING');
        }

        $clientId = trim((string) $row->client_id);
        $clientSecret = trim((string) $row->client_secret);

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('OAUTH_PROVIDER_MISCONFIGURED');
        }

        $this->assertClientIdShape($alias, $clientId);

        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'alias' => (string) $row->alias,
            'name' => (string) $row->name,
        ];
    }

    /**
     * Safe operator/status view — never includes secret material.
     *
     * @return array{
     *   provider: string,
     *   row_present: bool,
     *   status_enabled: bool,
     *   client_id: 'SET'|'MISSING',
     *   client_secret: 'SET'|'MISSING',
     *   client_id_format: 'VALID_FORMAT'|'INVALID_FORMAT'|'MISSING',
     *   redirect_uri: string,
     *   credential_env_override: bool
     * }
     */
    public function operatorStatus(string $provider): array
    {
        $alias = OAuthProviders::normalize($provider);
        /** @var SocialAuthSystem|null $row */
        $row = SocialAuthSystem::query()->where('alias', $alias)->first();

        $clientId = $row ? trim((string) $row->client_id) : '';
        $clientSecret = $row ? trim((string) $row->client_secret) : '';
        $format = 'MISSING';
        if ($clientId !== '') {
            try {
                $this->assertClientIdShape($alias, $clientId);
                $format = 'VALID_FORMAT';
            } catch (RuntimeException) {
                $format = 'INVALID_FORMAT';
            }
        }

        return [
            'provider' => $alias,
            'row_present' => $row !== null,
            'status_enabled' => $row !== null && (int) $row->status === 1,
            'client_id' => $clientId !== '' ? 'SET' : 'MISSING',
            'client_secret' => $clientSecret !== '' ? 'SET' : 'MISSING',
            'client_id_format' => $format,
            'redirect_uri' => $this->redirectUri($alias),
            'credential_env_override' => $this->hasCredentialEnvOverride($alias),
        ];
    }

    public function redirectUri(string $provider): string
    {
        $alias = OAuthProviders::normalize($provider);
        $envKey = 'EXS_OAUTH_'.strtoupper($alias).'_REDIRECT_URI';
        $configured = trim((string) env($envKey, ''));

        if ($configured !== '') {
            return $configured;
        }

        $base = rtrim((string) config('app.url'), '/');

        // Prefer public apex /apis path used by owned frontend BFF.
        if (str_contains($base, 'app.exswaping.com')) {
            $base = 'https://exswaping.com';
        }

        return $base.'/apis/client-api/v1/auth/oauth/'.$alias.'/callback';
    }

    private function assertClientIdShape(string $alias, string $clientId): void
    {
        if ($alias === OAuthProviders::GOOGLE) {
            // Web application client IDs used with authorization-code + secret.
            if (!preg_match('/^[0-9]+-[a-z0-9]+\.apps\.googleusercontent\.com$/i', $clientId)) {
                throw new RuntimeException('OAUTH_GOOGLE_CLIENT_ID_SHAPE');
            }

            return;
        }

        if ($alias === OAuthProviders::YANDEX) {
            if (!preg_match('/^[a-f0-9]{32}$/i', $clientId)) {
                throw new RuntimeException('OAUTH_YANDEX_CLIENT_ID_SHAPE');
            }
        }
    }

    private function assertNoCredentialEnvOverrides(string $alias): void
    {
        if ($this->hasCredentialEnvOverride($alias)) {
            throw new RuntimeException('OAUTH_CREDENTIAL_ENV_OVERRIDE_FORBIDDEN');
        }
    }

    private function hasCredentialEnvOverride(string $alias): bool
    {
        $upper = strtoupper($alias);
        foreach ([
            'EXS_OAUTH_'.$upper.'_CLIENT_ID',
            'EXS_OAUTH_'.$upper.'_CLIENT_SECRET',
            $upper.'_CLIENT_ID',
            $upper.'_CLIENT_SECRET',
        ] as $key) {
            $value = env($key);
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }
}
