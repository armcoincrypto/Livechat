<?php

namespace App\Http\Middleware;

use App\Models\Banned;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BlacklistMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $ip = (string) $request->ip();
        if ($ip === '') {
            return $next($request);
        }

        $now = now();

        // 1) IPv4: match by range (ip / cidr)
        // 2) Fallback: exact match by filter_key (only for ip-type to avoid accidental matches with email/domain)
        $bannedItem = null;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            if ($ipLong !== false) {
                $ipInt = (int) sprintf('%u', $ipLong);

                $bannedItem = Banned::query()
                    ->where('expired_at', '>=', $now)
                    ->where(function ($q) use ($ipInt, $ip) {
                        $q->where(function ($qq) use ($ipInt) {
                            $qq->whereIn('type', ['ip', 'cidr'])
                                ->whereNotNull('ip_from')
                                ->whereNotNull('ip_to')
                                ->where('ip_from', '<=', $ipInt)
                                ->where('ip_to', '>=', $ipInt);
                        })
                            ->orWhere(function ($qq) use ($ip) {
                                $qq->where('type', 'ip')
                                    ->where('filter_key', mb_strtolower(trim($ip)));
                            });
                    })
                    ->orderByDesc('id')
                    ->first(['id', 'type', 'filter_name', 'expired_at', 'description']);
            }
        } else {
            // If later you'll add IPv6 ranges (ip6_from/ip6_to), extend here.
            // For now, we can still do an exact match (if you store IPv6 as filter_key with type=ip).
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $bannedItem = Banned::query()
                    ->where('type', 'ip')
                    ->where('filter_key', mb_strtolower(trim($ip)))
                    ->where('expired_at', '>=', $now)
                    ->orderByDesc('id')
                    ->first(['id', 'type', 'filter_name', 'expired_at', 'description']);
            }
        }

        if ($bannedItem !== null) {
            return response()->json([
                'status' => 1,
                'is_banned_ip' => true,
                'code' => 'banned_ip',
                'message' => 'Доступ ограничен: ваш IP-адрес находится в черном списке.',
                'item' => [
                    'type' => (string) ($bannedItem->type ?? ''),
                    'filter' => (string) ($bannedItem->filter_name ?? ''),
                    'expired_at' => Carbon::parse($bannedItem->expired_at)->translatedFormat('j F Y, H:i'),
                    'comment' => (string) ($bannedItem->description ?? ''),
                ],
            ], 403);
        }

        return $next($request);
    }
}
