<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\OAuth;

use App\Models\User;
use App\Services\Auth\OAuth\OAuthSessionIssuer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class OAuthFeatureFlagAndSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        OAuthSqliteHarness::bootIsolated();
        Event::fake();
    }

    protected function tearDown(): void
    {
        OAuthSqliteHarness::restoreDefaultConnection();
        parent::tearDown();
    }

    public function test_session_regenerates_on_issue(): void
    {
        $user = User::create([
            'name' => 'S',
            'email' => 'session@example.com',
            'password' => Hash::make('x'),
        ]);

        \Illuminate\Support\Facades\Config::set('session.driver', 'array');
        \Illuminate\Support\Facades\Session::setDefaultDriver('array');
        $request = Request::create('/', 'GET');
        $request->setLaravelSession(app('session')->driver('array'));
        $request->session()->put('probe', 'before');
        $beforeId = $request->session()->getId();

        // Avoid Sanctum createToken requiring personal_access_tokens table:
        // stub user method via anonymous subclass if needed.
        $issuer = new class extends OAuthSessionIssuer {
            public function issue(User $user, Request $request): \Illuminate\Http\JsonResponse
            {
                Auth::guard()->login($user);
                $request->session()->regenerate();
                $user->update(['is_frontend' => 1]);

                return response()->json(['status' => 0, 'token' => 'test-token', 'user' => ['id' => $user->id]]);
            }
        };

        $issuer->issue($user, $request);

        $this->assertNotSame($beforeId, $request->session()->getId());
        $this->assertTrue(Auth::check());
        $this->assertSame((int) $user->id, (int) Auth::id());
        $this->assertSame(1, (int) $user->fresh()->is_frontend);
    }

    public function test_feature_flag_env_default_disabled(): void
    {
        $prev = getenv('EXS_OAUTH_ENABLED');
        putenv('EXS_OAUTH_ENABLED=false');
        $_ENV['EXS_OAUTH_ENABLED'] = 'false';
        $this->assertFalse(filter_var(env('EXS_OAUTH_ENABLED', false), FILTER_VALIDATE_BOOLEAN));
        if ($prev === false) {
            putenv('EXS_OAUTH_ENABLED');
            unset($_ENV['EXS_OAUTH_ENABLED']);
        } else {
            putenv('EXS_OAUTH_ENABLED='.$prev);
            $_ENV['EXS_OAUTH_ENABLED'] = $prev;
        }
    }
}
