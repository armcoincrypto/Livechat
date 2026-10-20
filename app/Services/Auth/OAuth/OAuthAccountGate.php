<?php

declare(strict_types=1);

namespace App\Services\Auth\OAuth;

use App\Models\User;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Same effective restrictions as password LoginController.
 * Does NOT reactivate deactivated accounts.
 */
final class OAuthAccountGate
{
    public function assertCanAuthenticate(User $user, Request $request): void
    {
        if ((int) $user->deactivation === 1) {
            throw new RuntimeException('ACCOUNT_DEACTIVATED');
        }

        if (method_exists($user, 'isBanned') && $user->isBanned()) {
            throw new RuntimeException('ACCOUNT_BANNED');
        }

        if (!empty($user->allowed_ip_addresses)) {
            $allowedIps = array_map(
                'trim',
                explode(',', preg_replace('~[\r\n]+~', '', (string) $user->allowed_ip_addresses) ?? '')
            );

            if (!IpUtils::checkIp($request->ip(), $allowedIps)) {
                throw new RuntimeException('ACCOUNT_IP_NOT_ALLOWED');
            }
        }
    }
}
