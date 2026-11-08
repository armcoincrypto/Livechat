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
        if (!$this->isFresh($request)) {
            throw new RuntimeException('OAUTH_REAUTH_REQUIRED');
        }
    }

    public function isFresh(Request $request): bool
    {
        $at = (int) $request->session()->get(self::SESSION_KEY, 0);

        return $at > 0 && (time() - $at) <= self::TTL_SECONDS;
    }

    /**
     * Mark reauth fresh (password confirm, or successful OAuth login/register).
     */
    public function markFresh(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, time());
        $request->session()->put('auth.password_confirmed_at', time());
    }

    public function confirmPassword(Request $request, string $password): void
    {
        $user = $request->user();
        if (!$user) {
            throw new RuntimeException('OAUTH_LINK_AUTH_REQUIRED');
        }

        if ($this->userHasPlaceholderPassword($user)) {
            throw new RuntimeException('OAUTH_PASSWORD_NOT_SET');
        }

        $hash = (string) $user->getAuthPassword();
        if ($hash === '' || !Hash::check($password, $hash)) {
            throw new RuntimeException('OAUTH_REAUTH_FAILED');
        }

        $this->markFresh($request);
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public function userHasPlaceholderPassword(object $user): bool
    {
        return (bool) ($user->oauth_password_is_placeholder ?? false);
    }

    public function clearPlaceholderPassword(object $user): void
    {
        if (!($user->oauth_password_is_placeholder ?? false)) {
            return;
        }
        $user->forceFill(['oauth_password_is_placeholder' => false])->save();
    }
}
