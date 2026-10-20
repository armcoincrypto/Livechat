<?php

declare(strict_types=1);

namespace App\Services\Auth\OAuth;

use App\Models\SocialAuthSystem;
use RuntimeException;

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

        /** @var SocialAuthSystem|null $row */
        $row = SocialAuthSystem::query()
            ->where('alias', $alias)
            ->where('status', 1)
            ->first();

        if (!$row) {
            throw new RuntimeException('OAUTH_PROVIDER_DISABLED');
        }

        $clientId = trim((string) $row->client_id);
        $clientSecret = trim((string) $row->client_secret);

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('OAUTH_PROVIDER_MISCONFIGURED');
        }

        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'alias' => (string) $row->alias,
            'name' => (string) $row->name,
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
}
