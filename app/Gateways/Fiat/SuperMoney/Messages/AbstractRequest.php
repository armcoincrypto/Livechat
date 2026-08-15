<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\SuperMoney\Messages;

use iEXPackages\Payments\Core\Engine\AbstractRequest as BaseAbstractRequest;

/**
 * Базовый Request для шлюза SuperMoney.
 *
 * Здесь:
 * - подключаются Generated*Inputs (позже генератором)
 * - задаётся базовый endpoint (можно статически или из конфига)
 * - создаётся HttpClient и единый callApi() для всех операций
 */
abstract class AbstractRequest extends BaseAbstractRequest
{
    // Пример подключения (когда сгенерируешь):
    use Traits\GeneratedMerchantInputs,
        Traits\GeneratedPayInputs;

    /**
     * Базовый URL (по умолчанию шаблонный).
     *
     * В каждом шлюзе ты сам решаешь:
     * - статический хост: вернуть 'https://example.com'
     * - из конфига: return 'https://' . $this->configString('api_host')
     * - кастомно: переопределить как нужно
     */
    protected function endpointStaticBaseUrl(): string
    {
        return $this->getConnectionValue('getApiDomain');
    }

    /**
     * HTTP-конфигурация SuperMoney.
     *
     * Особенности:
     * - Bearer token (api_auth_token) -> token
     * - X-Signature = HMAC_SHA256(bodyJson + PATH + QUERY, api_sign_token)
     * - GET: params в query, body пустой
     */
    protected function httpConfig(): array
    {
        return [
            'baseUrl' => $this->endpointUrl('/'),
            'token'   => $this->getConnectionValue('getApiAuthToken'),

            'timeout'      => 10,
            'retries'      => 3,
            'retryDelayMs' => 1000,

            'headers' => [
                'Accept' => 'application/json',
            ],

            'beforeSend' => function (
                string $method,
                string $path,
                array $data,
                array $headers
            ) {
                $method = strtolower($method);
                $path = $this->normalizePath($path);

                // GET: переносим data в query-string, body в запросе будет пустой
                $bodyJson = '';

                if ($method === 'get') {
                    if (!empty($data)) {
                        $queryString = http_build_query($data, '', '&', PHP_QUERY_RFC3986);
                        $path .= (str_contains($path, '?') ? '&' : '?') . $queryString;
                    }

                    $data = [];
                    $bodyJson = '';
                } else {
                    $bodyJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if (!is_string($bodyJson)) {
                        throw new \Exception('Не удалось сформировать JSON для подписи.');
                    }
                }

                $headers['X-Signature'] = $this->calculateSignature(
                    urlOrPath: $path,
                    requestJson: $bodyJson,
                    secret: $this->getConnectionValue('getApiSignToken')
                );

                return [$path, $data, $headers];
            },
        ];
    }

    /**
     * Подпись по спецификации SuperMoney:
     * signatureString = bodyJson + PATH + QUERY (query без '?').
     */
    private function calculateSignature(string $urlOrPath, string $requestJson, string $secret): string
    {
        $path  = (string) (parse_url($urlOrPath, PHP_URL_PATH) ?? '');
        $query = (string) (parse_url($urlOrPath, PHP_URL_QUERY) ?? '');

        $signatureString = $requestJson . $path . $query;

        return hash_hmac('sha256', $signatureString, $secret);
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }

        return str_starts_with($path, '/') ? $path : '/' . $path;
    }
}
