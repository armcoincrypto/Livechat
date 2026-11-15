<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * Guest middleware for browser pages redirects home. JSON / client-api
     * callers (owned BFF login) must not receive HTML 302s — those become
     * AUTH_BAD_GATEWAY and strand users on /auth/login?returnTo=/account.
     *
     * @param Request $request
     * @param Closure $next
     * @param string ...$guards
     * @return Response
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                if ($this->wantsJsonAuthResponse($request)) {
                    return response()->json([
                        'status' => 0,
                        'code' => 'ALREADY_AUTHENTICATED',
                        'message' => 'Already authenticated',
                    ]);
                }

                return redirect()->to('/');
            }
        }

        return $next($request);
    }

    private function wantsJsonAuthResponse(Request $request): bool
    {
        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return true;
        }

        $accept = (string) $request->header('Accept', '');
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        $path = '/'.$request->path();
        return str_contains($path, '/client-api/')
            || str_contains($path, '/apis/client-api/');
    }
}
