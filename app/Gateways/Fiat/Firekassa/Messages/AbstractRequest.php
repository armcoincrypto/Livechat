<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Firekassa\Messages;

use iEXPackages\Payments\Core\Engine\AbstractRequest as BaseAbstractRequest;
use App\Gateways\Fiat\Firekassa\Services\HttpClient;
use iEXPackages\Payments\Core\Support\UrlHelper;

/**
 * Базовый Request для шлюза Firekassa.
 *
 * Здесь:
 * - подключаются Generated*Inputs (позже генератором)
 * - задаётся базовый endpoint (можно статически или из конфига)
 * - создаётся HttpClient и единый callApi() для всех операций
 */
abstract class AbstractRequest extends BaseAbstractRequest
{
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
        if ($this->isMerchantContext()) {
            return UrlHelper::toHttpsBaseUrl($this->getSiteUrl());
        }

        return UrlHelper::toHttpsBaseUrl($this->getPaySiteUrl());
    }

    /**
     * HTTP-конфигурация для FiatCut.
     *
     * Особенности:
     * - Access-Token в headers
     * - Accept: application/json
     */
    protected function httpConfig(): array
    {
        return [
            'baseUrl' => $this->endpointUrl('/'),
            'token'   => $this->getConnectionValue('getSecretKey'),

            'headers' => [
                'Accept'       => 'application/json'
            ],

            'timeout'      => 10,
            'retries'      => 3,
            'retryDelayMs' => 1000,

            'beforeSend' => function (
                string $method,
                string $path,
                array $data,
                array $headers
            ): array {
                $method = strtolower($method);

                // GET: тело отсутствует, подпись = path + ?query
                if ($method === 'get') {
                    if (!empty($data)) {
                        $query = http_build_query($data, '', '&', PHP_QUERY_RFC3986);
                        $pathWithQuery = $query !== '' ? ($path . '?' . $query) : $path;

                        $headers['Signature'] = hash_hmac(
                            'sha512',
                            $pathWithQuery,
                            $this->getConnectionString('getSignToken')
                        );

                        // важно: GET остаётся с data как query, не переносим в path вручную
                        return [$path, $data, $headers];
                    }

                    $headers['Signature'] = hash_hmac(
                        'sha512',
                        $path,
                        $this->getConnectionString('getSignToken')
                    );

                    return [$path, $data, $headers];
                }

                // POST/PUT/PATCH/DELETE: подпись = path + bodyJson (если есть)
                $body = empty($data)
                    ? ''
                    : json_encode($data);

                $payload = $path . ($body ?: '');


                $headers['Signature'] = hash_hmac(
                    'sha512',
                    $payload,
                    $this->getConnectionString('getSignToken')
                );

                return [$path, $data, $headers];
            },
        ];
    }
}
