<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\OAuth;

use App\Models\User;
use App\Models\UserOAuthIdentity;
use App\Services\Auth\OAuth\OAuthAccountGate;
use App\Services\Auth\OAuth\OAuthIdentityResolver;
use App\Services\Auth\OAuth\OAuthProfile;
use App\Services\Auth\OAuth\OAuthProviders;
use App\Services\Auth\OAuth\OAuthReauthGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

final class OAuthIdentityResolverTest extends TestCase
{
    private OAuthIdentityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        OAuthSqliteHarness::bootIsolated();
        Event::fake();
        $this->resolver = new OAuthIdentityResolver(new OAuthAccountGate(), new OAuthReauthGuard());
    }

    protected function tearDown(): void
    {
        OAuthSqliteHarness::restoreDefaultConnection();
        parent::tearDown();
    }

    private function requestWithSession(array $server = []): Request
    {
        \Illuminate\Support\Facades\Config::set('session.driver', 'array');
        \Illuminate\Support\Facades\Session::setDefaultDriver('array');
        $request = Request::create('/', 'GET', [], [], [], array_merge(['REMOTE_ADDR' => '203.0.113.10'], $server));
        $request->setLaravelSession(app('session')->driver('array'));
        $request->session()->start();

        return $request;
    }

    public function test_google_new_user_register(): void
    {
        $request = $this->requestWithSession();
        $profile = new OAuthProfile(OAuthProviders::GOOGLE, 'g-sub-new', 'new@example.com', true, 'New User');

        $result = $this->resolver->resolve($profile, ['intent' => 'login'], $request);

        $this->assertSame('register', $result['outcome']);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertSame('new@example.com', $result['user']->email);
        $this->assertNotNull($result['user']->email_verified_at);
        $this->assertSame(1, UserOAuthIdentity::query()->count());
    }

    public function test_google_returning_user_via_identity_table(): void
    {
        $user = $this->makeUser(['email' => 'ret@example.com']);
        UserOAuthIdentity::create([
            'user_id' => $user->id,
            'provider' => OAuthProviders::GOOGLE,
            'provider_subject' => 'g-sub-ret',
            'provider_email' => 'ret@example.com',
            'provider_email_verified' => true,
        ]);

        $request = $this->requestWithSession();
        $profile = new OAuthProfile(OAuthProviders::GOOGLE, 'g-sub-ret', 'ret@example.com', true, 'Ret');

        $result = $this->resolver->resolve($profile, ['intent' => 'login'], $request);
        $this->assertSame('login', $result['outcome']);
        $this->assertSame((int) $user->id, (int) $result['user']->id);
        $this->assertSame(1, User::query()->count());
    }

    public function test_historical_google_legacy_provider_columns(): void
    {
        $user = $this->makeUser([
            'email' => 'legacy@example.com',
            'provider' => OAuthProviders::GOOGLE,
            'provider_id' => 'g-legacy-sub',
        ]);

        $request = $this->requestWithSession();
        $profile = new OAuthProfile(OAuthProviders::GOOGLE, 'g-legacy-sub', 'legacy@example.com', true, 'Legacy');

        $result = $this->resolver->resolve($profile, ['intent' => 'login'], $request);
        $this->assertSame('login', $result['outcome']);
        $this->assertSame((int) $user->id, (int) $result['user']->id);
        $this->assertSame(1, UserOAuthIdentity::query()->where('provider_subject', 'g-legacy-sub')->count());
    }

    public function test_deactivated_historical_google_denied(): void
    {
        $this->makeUser([
            'email' => 'dead@example.com',
            'provider' => OAuthProviders::GOOGLE,
            'provider_id' => 'g-dead',
            'deactivation' => 1,
        ]);

        $request = $this->requestWithSession();
        $profile = new OAuthProfile(OAuthProviders::GOOGLE, 'g-dead', 'dead@example.com', true, 'Dead');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ACCOUNT_DEACTIVATED');
        $this->resolver->resolve($profile, ['intent' => 'login'], $request);
    }

    public function test_yandex_new_and_returning(): void
    {
        $request = $this->requestWithSession();
        $profile = new OAuthProfile(OAuthProviders::YANDEX, 'y-1', 'y@example.com', null, 'Y');

        $created = $this->resolver->resolve($profile, ['intent' => 'login'], $request);
        $this->assertSame('register', $created['outcome']);
        $this->assertNull($created['user']->email_verified_at);

        $again = $this->resolver->resolve($profile, ['intent' => 'login'], $request);
        $this->assertSame('login', $again['outcome']);
        $this->assertSame((int) $created['user']->id, (int) $again['user']->id);
    }

    public function test_password_account_email_collision_requires_link(): void
    {
        $this->makeUser(['email' => 'pw@example.com']);

        $request = $this->requestWithSession();
        $profile = new OAuthProfile(OAuthProviders::GOOGLE, 'g-collision', 'pw@example.com', true, 'X');

        $result = $this->resolver->resolve($profile, ['intent' => 'login'], $request);
        $this->assertSame('link_required', $result['outcome']);
        $this->assertSame('OAUTH_LINK_REQUIRED', $result['code']);
        $this->assertSame(0, UserOAuthIdentity::query()->count());
    }

    public function test_explicit_link_requires_reauth_and_session_user(): void
    {
        $user = $this->makeUser(['email' => 'linkme@example.com']);
        $request = $this->requestWithSession();
        $request->setUserResolver(static fn () => $user);

        $profile = new OAuthProfile(OAuthProviders::GOOGLE, 'g-link', 'other@example.com', true, 'L');

        try {
            $this->resolver->resolve($profile, [
                'intent' => 'link',
                'link_user_id' => $user->id,
            ], $request);
            $this->fail('expected reauth');
        } catch (RuntimeException $e) {
            $this->assertSame('OAUTH_REAUTH_REQUIRED', $e->getMessage());
        }

        $request->session()->put(OAuthReauthGuard::SESSION_KEY, time());
        $result = $this->resolver->resolve($profile, [
            'intent' => 'link',
            'link_user_id' => $user->id,
        ], $request);
        $this->assertSame('link', $result['outcome']);
        $this->assertSame(1, UserOAuthIdentity::query()->where('user_id', $user->id)->count());
    }

    public function test_unauthorized_link_wrong_user(): void
    {
        $owner = $this->makeUser(['email' => 'owner@example.com']);
        $other = $this->makeUser(['email' => 'other@example.com']);
        $request = $this->requestWithSession();
        $request->setUserResolver(static fn () => $other);
        $request->session()->put(OAuthReauthGuard::SESSION_KEY, time());

        $profile = new OAuthProfile(OAuthProviders::GOOGLE, 'g-hijack', 'x@example.com', true, 'H');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OAUTH_LINK_AUTH_REQUIRED');
        $this->resolver->resolve($profile, [
            'intent' => 'link',
            'link_user_id' => $owner->id,
        ], $request);
    }

    public function test_duplicate_subject_conflict_on_link(): void
    {
        $a = $this->makeUser(['email' => 'a@example.com']);
        $b = $this->makeUser(['email' => 'b@example.com']);
        UserOAuthIdentity::create([
            'user_id' => $a->id,
            'provider' => OAuthProviders::GOOGLE,
            'provider_subject' => 'shared-sub',
            'provider_email' => 'a@example.com',
            'provider_email_verified' => true,
        ]);

        $request = $this->requestWithSession();
        $request->setUserResolver(static fn () => $b);
        $request->session()->put(OAuthReauthGuard::SESSION_KEY, time());

        $profile = new OAuthProfile(OAuthProviders::GOOGLE, 'shared-sub', 'b@example.com', true, 'B');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OAUTH_SUBJECT_OWNED_BY_OTHER');
        $this->resolver->resolve($profile, [
            'intent' => 'link',
            'link_user_id' => $b->id,
        ], $request);
    }

    public function test_missing_and_unverified_email(): void
    {
        $request = $this->requestWithSession();

        try {
            $this->resolver->resolve(
                new OAuthProfile(OAuthProviders::GOOGLE, 'g-noemail', null, true, 'X'),
                ['intent' => 'login'],
                $request
            );
            $this->fail('missing email');
        } catch (RuntimeException $e) {
            $this->assertSame('OAUTH_EMAIL_REQUIRED', $e->getMessage());
        }

        try {
            $this->resolver->resolve(
                new OAuthProfile(OAuthProviders::GOOGLE, 'g-unverified', 'u@example.com', false, 'X'),
                ['intent' => 'login'],
                $request
            );
            $this->fail('unverified');
        } catch (RuntimeException $e) {
            $this->assertSame('OAUTH_EMAIL_UNVERIFIED', $e->getMessage());
        }
    }

    public function test_no_silent_email_linking_does_not_mutate_existing_user_id(): void
    {
        $existing = $this->makeUser(['email' => 'exists@example.com']);
        $beforeId = (int) $existing->id;

        $request = $this->requestWithSession();
        $result = $this->resolver->resolve(
            new OAuthProfile(OAuthProviders::YANDEX, 'y-new', 'exists@example.com', null, 'Y'),
            ['intent' => 'login'],
            $request
        );

        $this->assertSame('link_required', $result['outcome']);
        $this->assertSame($beforeId, (int) User::query()->where('email', 'exists@example.com')->value('id'));
        $this->assertSame(0, UserOAuthIdentity::query()->count());
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function makeUser(array $attrs): User
    {
        return User::create(array_merge([
            'name' => 'Test',
            'email' => 'user'.uniqid('', true).'@example.com',
            'password' => Hash::make('password-password-password'),
            'deactivation' => 0,
        ], $attrs));
    }
}
