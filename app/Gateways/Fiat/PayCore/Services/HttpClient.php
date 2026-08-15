<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\PayCore\Services;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * HttpClient для шлюза PayCore.
 *
 * Здесь ты концентрируешь:
 * - baseUrl/endpoint
 * - заголовки/токены/подпись
 * - timeout/retry
 * - логирование
 * - обработку исключений
 */
final class HttpClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeoutSeconds = 10,
        private readonly int $maxRetries = 3,
        private readonly int $retryDelayMs = 1000,
    ) {}

    /**
     * Выполнить HTTP запрос к API шлюза.
     *
     * @param string $method get|post|put|delete
     * @param string $path   /api/path
     * @param array  $data
     * @param string $format asJson|asForm
     * @param string|null $transactionId
     *
     * @throws Throwable
     */
    public function request(string $method, string $path, array $data = [], string $format = 'asJson', ?string $transactionId = null): array
    {
        try {
            $http = Http::timeout($this->timeoutSeconds)
                ->retry($this->maxRetries, $this->retryDelayMs)
                ->baseUrl(rtrim($this->baseUrl, '/'));

            $http = $format === 'asForm' ? $http->asForm() : $http->asJson();

            $response = $http->{$method}($path, $data)->throw()->json();

            Log::info('Gateway API Request', [
                'transaction_id' => $transactionId,
                'method' => strtoupper($method),
                'url' => $path,
                'request_data' => Arr::except($data, ['private_key', 'token']),
                'response' => $response,
            ]);

            return is_array($response) ? $response : [];

        } catch (ConnectionException $e) {
            Log::error('Ошибка соединения с API', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'path' => $path,
            ]);

            throw new Exception('Ошибка соединения с сервисом.');

        } catch (Throwable $e) {
            Log::critical('Критическая ошибка API', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'path' => $path,
            ]);

            throw new Exception('Критическая ошибка в API.');
        }
    }
}
