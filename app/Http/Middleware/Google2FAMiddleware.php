<?php

namespace App\Http\Middleware;

use Closure;
use PragmaRX\Google2FALaravel\Support\Authenticator;

class Google2FAMiddleware
{
    public function handle($request, Closure $next)
    {
        $authenticator = app(Authenticator::class)->boot($request);

        if ($authenticator->isAuthenticated()) {
            return $next($request);
        }

        return response()->json([
            'checkOTP' => false,
        ]);
    }
}
