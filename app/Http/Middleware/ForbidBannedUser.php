<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Cog\Contracts\Ban\Bannable as BannableContract;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ForbidBannedUser
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     *
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();
        if (isset($user) && $user instanceof BannableContract && $user->isBanned()) {
            return response()->json([
                'is_banned' => true,
                'records' => isset(auth()->user()->bans) ? auth()->user()->bans->map(function($item) {
                    return [
                        'id' => $item->id,
                        'attributes' => [
                            'is_permanent' => is_null($item->expired_at),
                            'expired_at' => Carbon::parse($item->expired_at)->translatedFormat('j F Y, H:i'),
                            'comment' => $item->comment,
                        ]
                    ];
                }) : []
            ], 403);
        }

        return $next($request);
    }
}
