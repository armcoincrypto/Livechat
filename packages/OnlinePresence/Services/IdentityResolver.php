<?php
declare(strict_types=1);

namespace iEXPackages\OnlinePresence\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

final class IdentityResolver
{
    public function resolve(Request $request): array
    {
        $user = $request->user();

        if ($user instanceof Authenticatable) {
            $userId = (int) ($user->getAuthIdentifier() ?? 0);

            return [
                'type' => 'user',
                'identity' => "user:{$userId}",
                'user_id' => $userId,
                'gid' => null,
                'user_name' => $this->safeString($user->name ?? null),
                'user_email' => $this->safeString($user->email ?? null),
            ];
        }

        $gid = $this->resolveGuestId($request);

        return [
            'type' => 'guest',
            'identity' => "guest:{$gid}",
            'user_id' => null,
            'gid' => $gid,
            'user_name' => null,
            'user_email' => null,
        ];
    }

    private function resolveGuestId(Request $request): string
    {
        $cookieName = (string) config('online_presence.guest_cookie', 'gid');
        $ttlDays = max(1, (int) config('online_presence.guest_cookie_ttl_days', 365));

        $gid = trim((string) $request->cookie($cookieName, ''));

        if ($gid === '' || strlen($gid) > 64) {
            $gid = (string) Str::ulid();

            Cookie::queue(cookie()->make(
                name: $cookieName,
                value: $gid,
                minutes: $ttlDays * 24 * 60,
                path: '/',
                domain: null,
                secure: true,
                httpOnly: true,
                raw: false,
                sameSite: 'Lax'
            ));
        }

        return $gid;
    }

    private function safeString(mixed $v): ?string
    {
        if (!is_string($v)) {
            return null;
        }
        $v = trim($v);
        return $v !== '' ? $v : null;
    }
}
