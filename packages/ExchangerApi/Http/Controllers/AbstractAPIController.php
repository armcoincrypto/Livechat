<?php

namespace iEXPackages\ExchangerApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class AbstractAPIController extends Controller
{
    protected ?string $api_key = null;

    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Утилита для постобработки ответа (например, очистка лишних полей пагинации).
     *
     * @param  array       $array
     * @param  string|null $mode
     * @return array
     */
    public function filter_column(array $array, ?string $mode = null): array
    {
        if ($mode === 'paginate') {
            if (isset($array['links'])) {
                unset($array['links']);
            }

            if (isset($array['meta']['links'])) {
                unset($array['meta']['links']);
            }

            if (isset($array['meta']['path'])) {
                unset($array['meta']['path']);
            }
        }

        return $array;
    }
    /**
     * Обёртка над JSON-ответом с автоматическим логированием.
     */
    public function viewJson($data, int $status = 200, array $headers = []): JsonResponse
    {
        $response = response()->json($data, $status, $headers);

        $this->addToLog([], $response);

        return $response;
    }
    /**
     * Записываем запрос и (опционально) ответ в лог.
     *
     * @param  array            $context  Дополнительные данные (status_code, response_data и т.п.)
     * @param  JsonResponse|null $response Полный JSON-ответ, если он уже сформирован.
     */
    public function addToLog(array $context = [], ?JsonResponse $response = null): void
    {
        // Собираем заголовки, приводим все ключи к нижнему регистру,
        // при необходимости маскируем чувствительные заголовки.
        $headers = [];
        foreach ($this->request->headers->all() as $head_k => $head_v) {
            $key = strtolower($head_k);
            $value = is_array($head_v) ? implode(',', $head_v) : $head_v;

            if (in_array($key, ['authorization', 'x-api-key', 'cookie'], true)) {
                $value = '***';
            }

            $headers[$key] = $value;
        }

        $statusCode   = $response ? $response->getStatusCode() : ($context['status_code'] ?? null);
        $responseData = $response ? $response->getContent() : ($context['response_data'] ?? null);

        ApiLog::create([
            'api_token'      => $this->request->bearerToken(),
            'api_action'     => $this->request->path(),
            'ip_address'     => $this->request->ip(),
            'headers'        => json_encode($headers, JSON_UNESCAPED_UNICODE),
            'post_data'      => json_encode($this->request->all(), JSON_UNESCAPED_UNICODE),
            'status_code'    => $statusCode,
            'response_data'  => $responseData,
        ]);
    }

    /**
     * Перехватываем вызовы несуществующих методов контроллера и отдаём понятный JSON-ответ.
     */
    public function __call($method, $parameters)
    {
        return response()->json([
            'status'  => 1,
            'message' => 'Method not found on API controller.',
            'method'  => $method,
        ], 404);
    }
}
