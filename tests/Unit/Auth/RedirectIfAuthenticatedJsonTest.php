<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Http\Middleware\RedirectIfAuthenticated;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tests\Unit\Auth\OAuth\OAuthSqliteHarness;

/**
 * Guard against guest middleware HTML 302s on client-api auth POSTs, and
 * against ALREADY_AUTHENTICATED short-circuits on admin frontend-api login
 * (shared Account cookie must be allowed to re-login as admin).
 */
final class RedirectIfAuthenticatedJsonTest extends TestCase
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

    public function test_client_api_returns_already_authenticated_instead_of_redirect(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'guest-json@example.com',
            'password' => Hash::make('secret'),
        ]);
        Auth::login($user);

        $request = Request::create('/apis/client-api/v1/auth/register', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $middleware = new RedirectIfAuthenticated();
        $response = $middleware->handle($request, static fn () => response('next'));

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($payload);
        $this->assertSame(0, $payload['status'] ?? null);
        $this->assertSame('ALREADY_AUTHENTICATED', $payload['code'] ?? null);
        $this->assertFalse($response->isRedirection());
    }

    public function test_admin_frontend_api_passes_through_when_already_authenticated(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'guest-admin-api@example.com',
            'password' => Hash::make('secret'),
        ]);
        Auth::login($user);

        $request = Request::create('/iexadmin/frontend-api/login', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);

        $middleware = new RedirectIfAuthenticated();
        $response = $middleware->handle($request, static fn () => response('admin-login-next', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('admin-login-next', $response->getContent());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertNotSame('ALREADY_AUTHENTICATED', $payload['code'] ?? null);
    }

    public function test_browser_request_still_redirects_home(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'guest-html@example.com',
            'password' => Hash::make('secret'),
        ]);
        Auth::login($user);

        $request = Request::create('/auth/register', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'text/html',
        ]);

        $middleware = new RedirectIfAuthenticated();
        $response = $middleware->handle($request, static fn () => response('next'));

        $this->assertTrue($response->isRedirection());
        $this->assertSame(url('/'), $response->headers->get('Location'));
    }
}
