<?php

declare(strict_types=1);

namespace App\Services\Auth\OAuth\Providers;

use App\Services\Auth\OAuth\GoogleIdTokenVerifier;
use App\Services\Auth\OAuth\OAuthConfigRepository;
use App\Services\Auth\OAuth\OAuthProfile;
use App\Services\Auth\OAuth\OAuthProviders;
use App\Services\Auth\OAuth\OAuthStateStore;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GoogleOAuthProvider
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function __construct(
        private readonly OAuthConfigRepository $config,
        private readonly GoogleIdTokenVerifier $idTokenVerifier,
    ) {
    }

    public function buildAuthorizationUrl(
        string $state,
        string $codeChallenge,
        string $nonce,
    ): string {
        $cfg = $this->config->getEnabledProvider(OAuthProviders::GOOGLE);

        $query = http_build_query([
            'client_id' => $cfg['client_id'],
            'redirect_uri' => $this->config->redirectUri(OAuthProviders::GOOGLE),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'access_type' => 'online',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);

        return self::AUTH_URL.'?'.$query;
    }

    public function exchangeAndProfile(string $code, string $codeVerifier, string $nonce): OAuthProfile
    {
        $cfg = $this->config->getEnabledProvider(OAuthProviders::GOOGLE);

        $tokenResponse = Http::asForm()
            ->timeout(10)
            ->post(self::TOKEN_URL, [
                'code' => $code,
                'client_id' => $cfg['client_id'],
                'client_secret' => $cfg['client_secret'],
                'redirect_uri' => $this->config->redirectUri(OAuthProviders::GOOGLE),
                'grant_type' => 'authorization_code',
                'code_verifier' => $codeVerifier,
            ]);

        if (!$tokenResponse->successful()) {
            throw new RuntimeException('OAUTH_GOOGLE_TOKEN_EXCHANGE');
        }

        $body = $tokenResponse->json();
        if (!is_array($body) || empty($body['id_token']) || !is_string($body['id_token'])) {
            throw new RuntimeException('OAUTH_GOOGLE_ID_TOKEN_MISSING');
        }

        // Access token intentionally discarded (not persisted / not logged).
        unset($body['access_token'], $body['refresh_token']);

        $claims = $this->idTokenVerifier->verify($body['id_token'], $cfg['client_id'], $nonce);

        $email = isset($claims['email']) && is_string($claims['email'])
            ? strtolower(trim($claims['email']))
            : null;
        $emailVerified = array_key_exists('email_verified', $claims)
            ? filter_var($claims['email_verified'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;

        $name = isset($claims['name']) && is_string($claims['name'])
            ? trim(strip_tags($claims['name']))
            : null;

        return new OAuthProfile(
            provider: OAuthProviders::GOOGLE,
            subject: (string) $claims['sub'],
            email: $email !== '' ? $email : null,
            emailVerified: $emailVerified,
            name: $name !== '' ? $name : null,
        );
    }
}
