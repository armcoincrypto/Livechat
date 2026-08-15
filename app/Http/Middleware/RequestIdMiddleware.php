<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class RequestIdMiddleware
{
    /**
     * Add the Request ID header if needed.
     *
     * @param  Request  $request  Request to be checked.
     * @param  null  $guard
     * @return \Illuminate\Http\Response
     *
     * @throws \Exception
     */
    public function handle(Request $request, \Closure $next, $guard = null)
    {
        $uuid = $request->headers->get('X-Request-ID');
        if (is_null($uuid)) {
            $uuid = Uuid::uuid4()->toString();
            $request->headers->set('X-Request-ID', $uuid);
        }
        $response = $next($request);
        $response->headers->set('X-Request-ID', $uuid);

        return $response;
    }
}
