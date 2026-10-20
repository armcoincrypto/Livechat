<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\OAuth;

use App\Models\User;
use App\Services\Auth\OAuth\OAuthReauthGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

final class OAuthReauthGuardTest extends TestCase
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

    public function test_password_confirm_and_expiry(): void
    {
        $user = User::create([
            'name' => 'R',
            'email' => 'reauth@example.com',
            'password' => Hash::make('CorrectHorseBatteryStaple1'),
        ]);
        \Illuminate\Support\Facades\Config::set('session.driver', 'array');
        \Illuminate\Support\Facades\Session::setDefaultDriver('array');
        \Illuminate\Support\Facades\Session::flush();
        $session = app('session')->driver('array');
        $session->flush();
        $request = Request::create('/apis/client-api/v1/auth/oauth/reauth', 'POST');
        $request->setLaravelSession($session);
        $request->setUserResolver(static fn () => $user);

        $guard = new OAuthReauthGuard();

        try {
            $guard->assertFresh($request);
            $this->fail('expected OAUTH_REAUTH_REQUIRED on cold session');
        } catch (RuntimeException $e) {
            $this->assertSame('OAUTH_REAUTH_REQUIRED', $e->getMessage());
        }

        try {
            $guard->confirmPassword($request, 'wrong');
            $this->fail('bad pw');
        } catch (RuntimeException $e) {
            $this->assertSame('OAUTH_REAUTH_FAILED', $e->getMessage());
        }

        $guard->confirmPassword($request, 'CorrectHorseBatteryStaple1');
        $guard->assertFresh($request);

        $request->session()->put(OAuthReauthGuard::SESSION_KEY, time() - OAuthReauthGuard::TTL_SECONDS - 5);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OAUTH_REAUTH_REQUIRED');
        $guard->assertFresh($request);
    }
}
