<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Services;

use App\Settings\GatewayConfig;
use iEXPackages\Payments\Core\Contracts\GatewayHttpClientInterface;
use iEXPackages\Payments\Logging\GatewayLogger;
use iEXPackages\Payments\Logging\GatewayLogContext;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Чистый HTTP-клиент платёжных шлюзов (без Sandbox/Replay).
 *
 * Задачи:
 *  - выполнить HTTP запрос (GET/POST/PUT/...)
 *  - применить хуки beforeSend/afterResponse из httpConfig()
 *  - единообразно логировать запрос/ответ/ошибку через GatewayLogger
 *
 * Важно:
 *  - Этот класс НЕ знает про sandbox/replay.
 *  - Sandbox добавляется отдельным декоратором SandboxHttpClientDecorator.
 */
final class GatewayHttpClient implements GatewayHttpClientInterface
{
    public function __construct(
        private readonly GatewayLogger $logger,
        private readonly GatewayConfig $config,
    ) {}

    /**
     * Универсальный HTTP-вызов шлюза.
     *
     * Ожидаемый формат $spec (httpConfig):
     * [
     *   'baseUrl' => 'https://api.example.com',               // обязательный
     *   'headers' => ['X-Api-Key' => '...'],                  // optional
     *   'token'   => '...',                                   // optional (Bearer)
     *   'timeout' => 10,                                      // optional
     *   'retries' => 3,                                       // optional
     *   'retryDelayMs' => 1000,                               // optional
     *
     *   // optional: хук до отправки (подпись, query, дополнительные headers)
     *   // должен вернуть массив ровно из 3 элементов: [$path, $data, $headers]
     *   'beforeSend' => function(string $method, string $path, array $data, array $headers): array {
     *       return [$path, $data, $headers];
     *   },
     *
     *   // optional: хук после получения JSON (валидация API-уровня, преобразование)
     *   // должен вернуть массив (нормализованный responseBody)
     *   'afterResponse' => function(array $json, int $httpStatus): array {
     *       return $json;
     *   },
     * ]
     *
     * @param array $spec
     * @param string $method HTTP-метод (get/post/put/delete/patch...)
     * @param string $path Путь запроса (/api/...)
     * @param array $data Тело запроса или query-параметры (в Laravel Http::get($path, $data))
     * @param GatewayLogContext $ctx Контекст для логов (gateway/operation/direction/taskId/merchantId/...)
     * @param string $format asJson|asForm
     *
     * @return array Ответ API (массив). Если API вернул не JSON — вернётся ['raw'=>...]
     *
     * @throws Throwable Любая ошибка соединения/HTTP/afterResponse пробрасывается наружу
     */
    public function send(
        array $spec,
        string $method,
        string $path,
        array $data,
        GatewayLogContext $ctx,
        string $format = 'asJson'
    ): array {
        $started = microtime(true);

        $baseUrl = rtrim((string)($spec['baseUrl'] ?? ''), '/');
        if ($baseUrl === '') {
            throw new \InvalidArgumentException('GatewayHttpClient: baseUrl is required.');
        }

        $headers = is_array($spec['headers'] ?? null) ? $spec['headers'] : [];
        $token   = $spec['token'] ?? null;

        $timeout = (int)($spec['timeout'] ?? 10);
        $retries = (int)($spec['retries'] ?? 3);
        $delayMs = (int)($spec['retryDelayMs'] ?? 1000);
        $options = is_array($spec['options'] ?? null) ? $spec['options'] : [];


        $method = strtolower(trim($method));
        $path   = $this->normalizePath($path);

        // beforeSend: подпись, модификация path/data/headers
        if (isset($spec['beforeSend']) && is_callable($spec['beforeSend'])) {
            $result = ($spec['beforeSend'])($method, $path, $data, $headers);

            if (is_array($result) && count($result) === 3) {
                [$path, $data, $headers] = $result;
                $path = $this->normalizePath((string) $path);
                $data = is_array($data) ? $data : [];
                $headers = is_array($headers) ? $headers : [];
            }
        }

        $fullUrl = $baseUrl . $path;

        try {
            $http = $this->buildRequest(
                baseUrl: $baseUrl,
                timeout: $timeout,
                retries: $retries,
                delayMs: $delayMs,
                format: $format
            );

            if (!empty($token)) {
                $http = $http->withToken((string) $token);
            }

            if (!empty($headers)) {
                $http = $http->withHeaders($headers);
            }

            if (!empty($options)) {
                $http = $http->withOptions($options);
            }

            // В Laravel Http client: get($path, $query) / post($path, $data)
            $resp = $http->{$method}($path, $data);

            $durationMs = (int) round((microtime(true) - $started) * 1000);

            $json = $resp->json();
            $responseBody = is_array($json) ? $json : ['raw' => $resp->body()];

            // afterResponse: API-level ошибки / нормализация
            if (isset($spec['afterResponse']) && is_callable($spec['afterResponse'])) {
                $responseBody = ($spec['afterResponse'])(
                    is_array($json) ? $json : [],
                    (int) $resp->status()
                );

                // гарантируем массив
                $responseBody = is_array($responseBody) ? $responseBody : [];
            }

            // Логируем всегда (и успех, и ошибку 4xx/5xx)
            if ($this->shouldLog($ctx)) {
                $this->logger->write([
                    ...$ctx->toArray(),
                    'http_method' => strtoupper($method),
                    'url' => $fullUrl,
                    'response_status' => (int)$resp->status(),
                    'duration_ms' => $durationMs,
                    'request_headers' => $headers,
                    'request_body' => $data,
                    'response_body' => $responseBody,
                    'is_sandbox' => 0,
                ]);
            }

            // 4xx/5xx — бросаем после логирования
            $resp->throw();

            return $responseBody;

        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);

            if ($this->shouldLog($ctx)) {
                $this->logger->write([
                    ...$ctx->toArray(),
                    'http_method' => strtoupper($method),
                    'url' => $fullUrl,
                    'duration_ms' => $durationMs,
                    'request_headers' => $headers,
                    'request_body' => $data,
                    'error_class' => $e::class,
                    'error_message' => $e->getMessage(),
                    'is_sandbox' => 0,
                ]);
            }

            throw $e;
        }
    }

    /**
     * Сборка базового PendingRequest.
     */
    private function buildRequest(
        string $baseUrl,
        int $timeout,
        int $retries,
        int $delayMs,
        string $format
    ): PendingRequest {
        $http = Http::timeout($timeout)
            ->retry($retries, $delayMs)
            ->baseUrl($baseUrl);

        return $format === 'asForm'
            ? $http->asForm()
            : $http->asJson();
    }

    /**
     * Нормализация пути запроса.
     *
     * @example ''      -> '/'
     * @example 'api/x' -> '/api/x'
     * @example '/x'    -> '/x'
     */
    private function normalizePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '/';
        }

        return str_starts_with($path, '/') ? $path : '/' . $path;
    }

    /**
     * Решение: логируем ли запросы в зависимости от направления.
     *
     * incoming  -> merchant logs
     * outgoing  -> payout logs
     * прочее    -> всегда логируем (health/service/etc)
     */
    private function shouldLog(GatewayLogContext $ctx): bool
    {
        // incoming → merchant
        if ($ctx->direction === 'incoming') {
            return $this->config->isMerchantLogDisabled();
        }

        // outgoing → payout
        if ($ctx->direction === 'outgoing') {
            return $this->config->isPayoutLogDisabled();
        }

        // service / options / health — логируем всегда
        return true;
    }
}
