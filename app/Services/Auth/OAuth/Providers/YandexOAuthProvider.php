<?php

declare(strict_types=1);

namespace App\Services\Auth\OAuth\Providers;

use App\Services\Auth\OAuth\OAuthConfigRepository;
use App\Services\Auth\OAuth\OAuthProfile;
use App\Services\Auth\OAuth\OAuthProviders;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class YandexOAuthProvider
{
    private const AUTH_URL = 'https://oauth.yandex.com/authorize';
    private const TOKEN_URL = 'https://oauth.yandex.com/token';
    private const INFO_URL = 'https://login.yandex.ru/info';

    public function __construct(
        private readonly OAuthConfigRepository $config,
    ) {
    }

    public function buildAuthorizationUrl(string $state, string $codeChallenge): string
    {
        $cfg = $this->config->getEnabledProvider(OAuthProviders::YANDEX);

        // login:info + login:email — minimum for identity + contact email.
        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $cfg['client_id'],
            'redirect_uri' => $this->config->redirectUri(OAuthProviders::YANDEX),
            'state' => $state,
            'force_confirm' => 'yes',
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        return self::AUTH_URL.'?'.$query;
    }

    public function exchangeAndProfile(string $code, string $codeVerifier): OAuthProfile
    {
        $cfg = $this->config->getEnabledProvider(OAuthProviders::YANDEX);

        $tokenResponse = Http::asForm()
            ->timeout(10)
            ->post(self::TOKEN_URL, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => $cfg['client_id'],
                'client_secret' => $cfg['client_secret'],
                'redirect_uri' => $this->config->redirectUri(OAuthProviders::YANDEX),
                'code_verifier' => $codeVerifier,
            ]);

        if (!$tokenResponse->successful()) {
            throw new RuntimeException('OAUTH_YANDEX_TOKEN_EXCHANGE');
        }

        $tokenBody = $tokenResponse->json();
        if (!is_array($tokenBody) || empty($tokenBody['access_token']) || !is_string($tokenBody['access_token'])) {
            throw new RuntimeException('OAUTH_YANDEX_ACCESS_TOKEN_MISSING');
        }

        $accessToken = $tokenBody['access_token'];
        // Do not retain refresh tokens.
        unset($tokenBody);

        $infoResponse = Http::timeout(10)
            ->withToken($accessToken, 'OAuth')
            ->acceptJson()
            ->get(self::INFO_URL, ['format' => 'json']);

        // Forget access token ASAP.
        unset($accessToken);

        if (!$infoResponse->successful()) {
            throw new RuntimeException('OAUTH_YANDEX_USERINFO');
        }

        $info = $infoResponse->json();
        if (!is_array($info)) {
            throw new RuntimeException('OAUTH_YANDEX_USERINFO_INVALID');
        }

        $subject = trim((string) ($info['id'] ?? $info['psuid'] ?? ''));
        if ($subject === '') {
            throw new RuntimeException('OAUTH_YANDEX_SUBJECT_MISSING');
        }

        $email = null;
        if (!empty($info['default_email']) && is_string($info['default_email'])) {
            $email = strtolower(trim($info['default_email']));
        } elseif (!empty($info['emails']) && is_array($info['emails'])) {
            $first = $info['emails'][0] ?? null;
            if (is_string($first)) {
                $email = strtolower(trim($first));
            }
        }
        if ($email === '') {
            $email = null;
        }

        // Conservative: Yandex does not expose Google-equivalent email_verified.
        $emailVerified = null;

        $displayName = null;
        if (!empty($info['display_name']) && is_string($info['display_name'])) {
            $displayName = trim(strip_tags($info['display_name']));
        } elseif (!empty($info['real_name']) && is_string($info['real_name'])) {
            $displayName = trim(strip_tags($info['real_name']));
        } elseif (!empty($info['login']) && is_string($info['login'])) {
            $displayName = trim(strip_tags($info['login']));
        }

        return new OAuthProfile(
            provider: OAuthProviders::YANDEX,
            subject: $subject,
            email: $email,
            emailVerified: $emailVerified,
            name: $displayName !== '' ? $displayName : null,
        );
    }
}
