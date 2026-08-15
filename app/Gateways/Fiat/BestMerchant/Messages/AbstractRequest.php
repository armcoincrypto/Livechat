<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\BestMerchant\Messages;

use iEXPackages\Payments\Core\Engine\AbstractRequest as BaseAbstractRequest;

/**
 * Базовый Request для шлюза BestMerchant.
 *
 * Здесь:
 * - подключаются Generated*Inputs (позже генератором)
 * - задаётся базовый endpoint (можно статически или из конфига)
 * - создаётся HttpClient и единый callApi() для всех операций
 */
abstract class AbstractRequest extends BaseAbstractRequest
{
    // Пример подключения (когда сгенерируешь):
    use Traits\GeneratedMerchantInputs;

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
        return 'https://bestmerchant.site';
    }

    /**
     * HTTP-конфигурация шлюза.
     *
     * Используется единым GatewayHttpClient.
     */
    protected function httpConfig(): array
    {
        return [
            'baseUrl' => $this->endpointUrl('/'),
            'token' => $this->getApiToken(),

            'headers' => [
                'Accept'        => 'application/json',
            ],

            'timeout'       => 10,
            'retries'       => 3,
            'retryDelayMs'  => 1000,

            /**
             * Нормализация ошибок BestMerchant:
             * API может вернуть НЕ JSON или ошибочный HTTP.
             */
            'normalizeResponse' => function ($response, $rawBody) {
                if (is_array($response)) {
                    return $response;
                }

                return [
                    'status'  => 0,
                    'message' => is_string($rawBody) && $rawBody !== ''
                        ? $rawBody
                        : 'Unknown error',
                ];
            },
        ];
    }
}
