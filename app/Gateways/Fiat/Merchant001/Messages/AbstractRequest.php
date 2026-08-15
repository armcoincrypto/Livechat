<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Merchant001\Messages;

use iEXPackages\Payments\Core\Engine\AbstractRequest as BaseAbstractRequest;
use App\Gateways\Fiat\Merchant001\Services\HttpClient;

/**
 * Базовый Request для шлюза Merchant001.
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
        return 'https://api.merchant001.io';
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

            'token' => $this->getConnectionValue('getApiToken'),
            'headers' => [
                'Accept'       => 'application/json',
            ],

            'timeout'      => 10,
            'retries'      => 3,
            'retryDelayMs' => 1000,
        ];
    }
}
