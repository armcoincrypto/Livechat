<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsReadingModeMiddleware
{
    protected array $methods = [
        'POST',
        'PUT',
        'DELETE',
    ];

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        // Отключаем в демо режиме
        if (!config('iexexchanger.is_reading_mode')) {
            return $next($request);
        }

        // В случае неудачи, переадресовываем
        if (! in_array($request->getMethod(), $this->methods)) {
            return $next($request);
        }

        abort(403, 'Это действие отключено в Demo версии');
    }
}
