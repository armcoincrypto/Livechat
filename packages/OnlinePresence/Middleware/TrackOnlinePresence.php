<?php
declare(strict_types=1);

namespace iEXPackages\OnlinePresence\Middleware;

use Closure;
use iEXPackages\OnlinePresence\Services\PresenceTracker;
use Illuminate\Http\Request;

final class TrackOnlinePresence
{
    public function __construct(
        private readonly PresenceTracker $tracker
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        try {
            $this->tracker->track($request);
        } catch (\Throwable) {
            // не ломаем запросы
        }

        return $response;
    }
}
