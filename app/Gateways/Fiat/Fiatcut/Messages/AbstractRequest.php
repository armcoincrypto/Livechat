<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Fiatcut\Messages;

use iEXPackages\Payments\Core\Engine\AbstractRequest as BaseAbstractRequest;

/**
 * Базовый Request для шлюза Fiatcut.
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
        return $this->getApiUrlAddress();
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

            'headers' => [
                'Accept'       => 'application/json',
                'Access-Token' => $this->getApiToken(),
            ],

            'timeout'      => 10,
            'retries'      => 3,
            'retryDelayMs' => 1000,
        ];
    }
}
