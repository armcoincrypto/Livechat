<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\OAuth;

use App\Models\User;
use App\Models\UserOAuthIdentity;
use App\Services\Auth\OAuth\OAuthReauthGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

final class OAuthUnlinkAndPlaceholderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        OAuthSqliteHarness::bootIsolated();
    }

    protected function tearDown(): void
    {
        OAuthSqliteHarness::restoreDefaultConnection();
        parent::tearDown();
    }

    public function test_placeholder_password_rejects_confirm_and_mark_fresh_works(): void
    {
        $user = User::create([
            'name' => 'O',
            'email' => 'oauth-only@example.com',
            'password' => Hash::make('random-unusable'),
            'oauth_password_is_placeholder' => true,
        ]);

        $session = app('session')->driver('array');
        $session->flush();
        $request = Request::create('/apis/client-api/v1/auth/oauth/reauth', 'POST');
        $request->setLaravelSession($session);
        $request->setUserResolver(static fn () => $user);

        $guard = new OAuthReauthGuard();
        $this->assertTrue($guard->userHasPlaceholderPassword($user));

        try {
            $guard->confirmPassword($request, 'anything');
            $this->fail('expected OAUTH_PASSWORD_NOT_SET');
        } catch (RuntimeException $e) {
            $this->assertSame('OAUTH_PASSWORD_NOT_SET', $e->getMessage());
        }

        $guard->markFresh($request);
        $this->assertTrue($guard->isFresh($request));
        $guard->assertFresh($request);
    }

    public function test_clear_placeholder_flag(): void
    {
        $user = User::create([
            'name' => 'O2',
            'email' => 'oauth-set@example.com',
            'password' => Hash::make('random'),
            'oauth_password_is_placeholder' => true,
        ]);
        $guard = new OAuthReauthGuard();
        $guard->clearPlaceholderPassword($user);
        $this->assertFalse((bool) $user->fresh()->oauth_password_is_placeholder);
    }

    public function test_identity_rows_unique_per_provider(): void
    {
        $user = User::create([
            'name' => 'L',
            'email' => 'link@example.com',
            'password' => Hash::make('Secret123'),
            'oauth_password_is_placeholder' => false,
        ]);
        UserOAuthIdentity::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_subject' => 'g-sub-1',
            'provider_email' => 'link@example.com',
            'provider_email_verified' => true,
        ]);
        $this->assertSame(1, UserOAuthIdentity::query()->where('user_id', $user->id)->count());
        UserOAuthIdentity::query()->where('user_id', $user->id)->where('provider', 'google')->delete();
        $this->assertSame(0, UserOAuthIdentity::query()->where('user_id', $user->id)->count());
    }
}
