<?php

namespace iEXPackages\ExchangerApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ApiLog;

class ApiLoggerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Выполняем контроллер и получаем ответ
        /** @var Response $response */
        $response = $next($request);

        // Маскируем чувствительные заголовки
        $headers = [];
        foreach ($request->headers->all() as $key => $value) {
            $val = is_array($value) ? implode(',', $value) : $value;

            if (in_array(strtolower($key), ['authorization', 'x-api-key', 'cookie'], true)) {
                $val = '***';
            }

            $headers[strtolower($key)] = $val;
        }

        $rawToken = $request->bearerToken();
        $tokenId = null;
        $tokenPlain = null;

        if ($rawToken && str_contains($rawToken, '|')) {
            // Формат Sanctum plain-text: "123|tokenvalue"
            [$tokenId, $tokenPlain] = explode('|', $rawToken, 2);
        } else {
            // Формат: обычный hash, без ID
            $tokenPlain = $rawToken;
        }

        ApiLog::create([
            'api_token' => $tokenPlain,
            'token_id'  => $tokenId,
            'api_action'    => $request->path(),
            'ip_address'    => $request->ip(),
            'headers'       => $headers,
            'post_data'     => $request->all(),
            'status_code'   => $response->getStatusCode(),
            'response_data' => json_decode($response->getContent(), true)
        ]);

        return $response;
    }
}
