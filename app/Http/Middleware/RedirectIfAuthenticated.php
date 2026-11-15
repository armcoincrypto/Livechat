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
     * Guest middleware for browser pages redirects home. Client-api BFF login
     * must not receive HTML 302s — those become AUTH_BAD_GATEWAY and strand
     * users on /auth/login?returnTo=/account.
     *
     * Admin frontend-api login must NOT get ALREADY_AUTHENTICATED either: the
     * admin SPA expects {checkOTP,user} from AuthorizationController, and an
     * Account session (shared cookie, is_frontend=1) must be allowed to
     * re-login so is_frontend flips to 0. Short-circuiting leaves the SPA on
     * a permanent wait / Forbidden state.
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
                if ($this->isClientApiPath($request)) {
                    return response()->json([
                        'status' => 0,
                        'code' => 'ALREADY_AUTHENTICATED',
                        'message' => 'Already authenticated',
                    ]);
                }

                if ($this->isAdminFrontendApiPath($request)) {
                    return $next($request);
                }

                return redirect()->to('/');
            }
        }

        return $next($request);
    }

    private function isClientApiPath(Request $request): bool
    {
        $path = '/'.$request->path();

        return str_contains($path, '/client-api/')
            || str_contains($path, '/apis/client-api/');
    }

    private function isAdminFrontendApiPath(Request $request): bool
    {
        $path = '/'.$request->path();

        return str_contains($path, '/frontend-api/');
    }
}
