<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Pshb\Messages;

use iEXPackages\Payments\Core\Engine\AbstractRequest as BaseAbstractRequest;

/**
 * Базовый Request для шлюза PayCore.
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
     * - кастомно: переопределить как нужно
     */
    protected function endpointStaticBaseUrl(): string
    {
        return $this->getApiDomain();
    }

    /**
     * ЕДИНАЯ точка конфигурации HTTP для шлюза PSHB.
     *
     * - baseUrl берётся строго из endpointStaticBaseUrl()
     * - никакие API-префиксы здесь не хардкодятся
     * - используется Engine HttpClient
     * - включено логирование для MerchantFlowLogger
     */
    protected function httpConfig(): array
    {
        return [
            // Базовый домен шлюза (из api_domain в config.php)
            'baseUrl' => rtrim($this->endpointStaticBaseUrl(), '/'),

            'headers' => [
                'Accept'          => 'application/json',
                'X-Gateway-Alias' => 'pshb',
            ],

            // Логирование на уровне Engine
            'log' => true,

            'timeout'      => 20,
            'retries'      => 3,
            'retryDelayMs' => 1000,
        ];
    }
}
