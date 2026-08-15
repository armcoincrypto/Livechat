<?php

namespace iEXPackages\ExchangerApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Response as JsonResponse;

class AccessRouteMiddleware
{
    /**
     * @param  \Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();

        if (! $user) {
            return JsonResponse::json([
                'status'  => 1,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Требуем наличие всех переданных abilities
        foreach ($abilities as $ability) {
            if (! $user->tokenCan($ability)) {
                return JsonResponse::json([
                    'status'  => 1,
                    'message' => 'You do not have access to this route',
                    'missing_ability' => $ability,
                ], 403);
            }
        }

        return $next($request);
    }
}
