<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminControlMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Account (is_frontend=1) shares iexexchanger_session on .exswaping.com.
     * Reject those sessions for admin APIs with 401 JSON so the SPA returns
     * to /iexadmin/login instead of hanging on a blank/Forbidden wait screen.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && (int) auth()->user()->is_frontend === 1) {
            return response()->json([
                'status' => 1,
                'code' => 'ACCOUNT_SESSION',
                'message' => 'Account session cannot access admin panel. Sign in again.',
                'isAuthenticated' => false,
            ], 401);
        }

        return $next($request);
    }
}
