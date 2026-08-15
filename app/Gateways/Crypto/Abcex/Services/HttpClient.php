<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Abcex\Services;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * HttpClient для шлюза Abcex.
 *
 * Что делает:
 * - baseUrl/timeout/retry
 * - asJson/asForm
 * - auth через Bearer Token (api_key)
 * - единое логирование (url/headers/payload/response)
 * - единая обработка ошибок шлюза (statusCode/message)
 */
final class HttpClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey = '',
        private readonly int $timeoutSeconds = 10,
        private readonly int $maxRetries = 3,
        private readonly int $retryDelayMs = 1000,
    ) {}

    /**
     * Выполнить HTTP запрос к API Abcex.
     *
     * @param string      $method         get|post|put|delete
     * @param string      $path           /api/path
     * @param array       $data
     * @param string      $format         asJson|asForm
     * @param string|null $transactionId  для логов
     *
     * @throws Throwable
     */
    public function request(
        string $method,
        string $path,
        array $data = [],
        string $format = 'asJson',
        ?string $transactionId = null
    ): array {
        $method = strtolower(trim($method));
        $path   = $this->normalizePath($path);

        try {
            $http = $this->buildRequest($format);

            // Авторизация как в старой версии: withToken(api_key)
            if ($this->apiKey !== '') {
                $http = $http->withToken($this->apiKey);
            }

            $response = $http->{$method}($path, $data);

            $json = $response->json();

            // Если не массив — ошибка (как было)
            if (!is_array($json)) {
                $this->logRequest(
                    transactionId: $transactionId,
                    method: $method,
                    path: $path,
                    payload: $data,
                    response: $json
                );

                throw new Exception('Данные не получены');
            }

            $this->logRequest(
                transactionId: $transactionId,
                method: $method,
                path: $path,
                payload: $data,
                response: $json
            );

            // Обработка ошибок Abcex по статусу в JSON (как у тебя было)
            if (isset($json['statusCode'])) {
                $message = (string)($json['message'] ?? 'Ошибка Abcex');
                throw new Exception($message);
            }

            // Можно также обрабатывать HTTP-коды, если надо:
            if ($response->failed()) {
                $message = (string)($json['message'] ?? $response->body() ?? 'Ошибка HTTP');
                throw new Exception($message);
            }

            return $json;

        } catch (ConnectionException $e) {
            $this->logException('Ошибка соединения с Abcex API', $transactionId, $method, $path, $data, $e);
            throw new Exception('Ошибка соединения с сервисом Abcex.');

        } catch (Throwable $e) {
            $this->logException('Критическая ошибка Abcex API', $transactionId, $method, $path, $data, $e);
            throw $e instanceof Exception ? $e : new Exception('Критическая ошибка в Abcex API.');
        }
    }

    /**
     * Сборка базового PendingRequest.
     */
    protected function buildRequest(string $format): PendingRequest
    {
        $http = Http::timeout($this->timeoutSeconds)
            ->retry($this->maxRetries, $this->retryDelayMs)
            ->baseUrl(rtrim($this->baseUrl, '/'));

        return $format === 'asForm' ? $http->asForm() : $http->asJson();
    }

    /**
     * Нормализация пути.
     */
    protected function normalizePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '/';
        }

        return str_starts_with($path, '/') ? $path : '/' . $path;
    }

    /**
     * Логирование запросов/ответов по аналогии со старой createLogRequest().
     * Здесь мы НЕ логируем секреты, только ключи.
     */
    protected function logRequest(
        ?string $transactionId,
        string $method,
        string $path,
        array $payload,
        mixed $response
    ): void {
        Log::info('Abcex API Request', [
            'transaction_id' => $transactionId,
            'method'         => strtoupper($method),
            'url'            => rtrim($this->baseUrl, '/') . $path,

            // В старой версии ты логировал headers и content — оставляем, но безопасно
            'headers'        => $this->apiKey !== '' ? ['Authorization' => 'Bearer ***'] : [],
            'content'        => Arr::except($payload, ['api_key', 'private_key', 'token']),
            'response'       => $response,
        ]);
    }

    protected function logException(
        string $title,
        ?string $transactionId,
        string $method,
        string $path,
        array $payload,
        Throwable $e
    ): void {
        Log::error($title, [
            'transaction_id' => $transactionId,
            'method'         => strtoupper($method),
            'url'            => rtrim($this->baseUrl, '/') . $path,
            'content'        => Arr::except($payload, ['api_key', 'private_key', 'token']),
            'error'          => $e->getMessage(),
        ]);
    }
}
