<?php

declare(strict_types=1);

namespace App\Services\Auth\OAuth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Recent reauthentication required before OAuth account linking.
 * Uses session key set only after password verification.
 */
final class OAuthReauthGuard
{
    public const SESSION_KEY = 'exs_oauth_reauth_at';
    public const TTL_SECONDS = 600;

    public function assertFresh(Request $request): void
    {
        $at = (int) $request->session()->get(self::SESSION_KEY, 0);
        if ($at <= 0 || (time() - $at) > self::TTL_SECONDS) {
            throw new RuntimeException('OAUTH_REAUTH_REQUIRED');
        }
    }

    public function confirmPassword(Request $request, string $password): void
    {
        $user = $request->user();
        if (!$user) {
            throw new RuntimeException('OAUTH_LINK_AUTH_REQUIRED');
        }

        $hash = (string) $user->getAuthPassword();
        if ($hash === '' || !Hash::check($password, $hash)) {
            throw new RuntimeException('OAUTH_REAUTH_FAILED');
        }

        $request->session()->put(self::SESSION_KEY, time());
        $request->session()->put('auth.password_confirmed_at', time());
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
