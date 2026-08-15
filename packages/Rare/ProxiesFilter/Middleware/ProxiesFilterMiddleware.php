<?php
namespace iEXPackages\Rare\ProxiesFilter\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProxiesFilterMiddleware
{
    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_FOR |
    Request::HEADER_X_FORWARDED_HOST |
    Request::HEADER_X_FORWARDED_PORT |
    Request::HEADER_X_FORWARDED_PROTO |
    Request::HEADER_X_FORWARDED_AWS_ELB;

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if(\Cache::has('proxyfilter.allowed'))
        {
            $proxies = Cache::get('proxyfilter.allowed', []);
            if (! empty($proxies)) {
                $request->setTrustedProxies($proxies, $this->headers);
            }
        }

        return $next($request);
    }
}
