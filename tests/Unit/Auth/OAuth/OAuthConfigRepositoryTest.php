<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\OAuth;

use App\Models\SocialAuthSystem;
use App\Services\Auth\OAuth\OAuthConfigRepository;
use App\Services\Auth\OAuth\OAuthProviders;
use App\Services\Auth\OAuth\Providers\GoogleOAuthProvider;
use App\Services\Auth\OAuth\Providers\YandexOAuthProvider;
use App\Services\Auth\OAuth\GoogleIdTokenVerifier;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class OAuthConfigRepositoryTest extends TestCase
{
    private OAuthConfigRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        OAuthSqliteHarness::bootIsolated();
        Config::set('app.url', 'https://exswaping.com');
        putenv('EXS_OAUTH_GOOGLE_CLIENT_ID');
        putenv('EXS_OAUTH_GOOGLE_CLIENT_SECRET');
        putenv('GOOGLE_CLIENT_ID');
        putenv('YANDEX_CLIENT_ID');
        unset($_ENV['EXS_OAUTH_GOOGLE_CLIENT_ID'], $_ENV['EXS_OAUTH_GOOGLE_CLIENT_SECRET'], $_ENV['GOOGLE_CLIENT_ID'], $_ENV['YANDEX_CLIENT_ID']);
        $this->repo = new OAuthConfigRepository();
    }

    protected function tearDown(): void
    {
        OAuthSqliteHarness::restoreDefaultConnection();
        parent::tearDown();
    }

    public function test_loads_admin_google_credentials_and_builds_redirect(): void
    {
        SocialAuthSystem::create([
            'name' => 'Google',
            'alias' => 'google',
            'client_id' => '123456789012-abcdefghijklmnopqrstuvwxyz012345.apps.googleusercontent.com',
            'client_secret' => 'GOCSPX-test-secret-not-real-xxxxx',
            'status' => 1,
            'sorting' => 1,
        ]);

        $cfg = $this->repo->getEnabledProvider(OAuthProviders::GOOGLE);
        $this->assertSame('google', $cfg['alias']);
        $this->assertStringEndsWith('.apps.googleusercontent.com', $cfg['client_id']);

        $url = (new GoogleOAuthProvider($this->repo, new GoogleIdTokenVerifier()))
            ->buildAuthorizationUrl('state-abc', 'challenge-xyz', 'nonce-1');

        $this->assertStringContainsString('accounts.google.com', $url);
        $this->assertStringContainsString('client_id='.rawurlencode($cfg['client_id']), $url);
        $this->assertStringContainsString('code_challenge=', $url);
        $this->assertStringContainsString('code_challenge_method=S256', $url);
        $this->assertStringNotContainsString($cfg['client_secret'], $url);
        $this->assertStringContainsString(
            rawurlencode('https://exswaping.com/apis/client-api/v1/auth/oauth/google/callback'),
            $url
        );
    }

    public function test_loads_admin_yandex_and_token_exchange_posts_secret(): void
    {
        SocialAuthSystem::create([
            'name' => 'Yandex',
            'alias' => 'yandex',
            'client_id' => str_repeat('ab', 16),
            'client_secret' => str_repeat('cd', 16),
            'status' => 1,
            'sorting' => 2,
        ]);

        $cfg = $this->repo->getEnabledProvider('YANDEX');
        $this->assertSame(32, strlen($cfg['client_id']));

        Http::fake([
            'oauth.yandex.com/token' => Http::response(['access_token' => 'atok'], 200),
            'login.yandex.ru/info*' => Http::response([
                'id' => 'y-sub-1',
                'default_email' => 'y@example.com',
                'display_name' => 'Y',
            ], 200),
        ]);

        $profile = (new YandexOAuthProvider($this->repo))->exchangeAndProfile('code-1', 'verifier-1');
        $this->assertSame('y-sub-1', $profile->subject);

        Http::assertSent(function ($request) use ($cfg) {
            if (!str_contains($request->url(), 'oauth.yandex.com/token')) {
                return true;
            }
            $body = $request->body();

            return str_contains($body, 'client_id='.$cfg['client_id'])
                && str_contains($body, 'client_secret='.$cfg['client_secret'])
                && str_contains($body, 'code_verifier=verifier-1');
        });
    }

    public function test_disabled_provider_fails_closed(): void
    {
        SocialAuthSystem::create([
            'name' => 'Google',
            'alias' => 'google',
            'client_id' => '123456789012-abcdefghijklmnopqrstuvwxyz012345.apps.googleusercontent.com',
            'client_secret' => 'secret-secret-secret-secret',
            'status' => 0,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OAUTH_PROVIDER_DISABLED');
        $this->repo->getEnabledProvider('google');
    }

    public function test_missing_credentials_fail_closed(): void
    {
        SocialAuthSystem::create([
            'name' => 'Google',
            'alias' => 'google',
            'client_id' => '',
            'client_secret' => '',
            'status' => 1,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OAUTH_PROVIDER_MISCONFIGURED');
        $this->repo->getEnabledProvider('google');
    }

    public function test_operator_status_never_includes_secrets(): void
    {
        SocialAuthSystem::create([
            'name' => 'Google',
            'alias' => 'google',
            'client_id' => '123456789012-abcdefghijklmnopqrstuvwxyz012345.apps.googleusercontent.com',
            'client_secret' => 'super-secret-value-do-not-leak',
            'status' => 1,
        ]);

        $status = $this->repo->operatorStatus('google');
        $json = json_encode($status, JSON_THROW_ON_ERROR);
        $this->assertSame('SET', $status['client_id']);
        $this->assertSame('SET', $status['client_secret']);
        $this->assertSame('VALID_FORMAT', $status['client_id_format']);
        $this->assertStringNotContainsString('super-secret-value-do-not-leak', $json);
        $this->assertStringNotContainsString('apps.googleusercontent.com', $json);
    }

    public function test_credential_env_override_forbidden(): void
    {
        SocialAuthSystem::create([
            'name' => 'Google',
            'alias' => 'google',
            'client_id' => '123456789012-abcdefghijklmnopqrstuvwxyz012345.apps.googleusercontent.com',
            'client_secret' => 'secret-secret-secret-secret',
            'status' => 1,
        ]);
        putenv('EXS_OAUTH_GOOGLE_CLIENT_ID=should-not-be-used');
        $_ENV['EXS_OAUTH_GOOGLE_CLIENT_ID'] = 'should-not-be-used';

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('OAUTH_CREDENTIAL_ENV_OVERRIDE_FORBIDDEN');
            $this->repo->getEnabledProvider('google');
        } finally {
            putenv('EXS_OAUTH_GOOGLE_CLIENT_ID');
            unset($_ENV['EXS_OAUTH_GOOGLE_CLIENT_ID']);
        }
    }

    public function test_redirect_uri_defaults_to_expected_callbacks(): void
    {
        $this->assertSame(
            'https://exswaping.com/apis/client-api/v1/auth/oauth/google/callback',
            $this->repo->redirectUri('google')
        );
        $this->assertSame(
            'https://exswaping.com/apis/client-api/v1/auth/oauth/yandex/callback',
            $this->repo->redirectUri('yandex')
        );
    }
}
