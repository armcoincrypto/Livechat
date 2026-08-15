<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\EvoPay\Messages;

use iEXPackages\Payments\Core\Engine\AbstractRequest as BaseAbstractRequest;

/**
 * Базовый Request для шлюза EvoPay.
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
        return 'https://v2-api.evo-pay.net';
    }

    /**
     * HTTP-конфигурация для Bydex.
     *
     * Особенности:
     * - авторизация не Bearer, а заголовки X-API-LOGIN + X-API-KEY
     */
    protected function httpConfig(): array
    {
        return [
            'baseUrl' => $this->endpointUrl('/'),

            'headers' => [
                'X-Api-Key' => $this->getApiToken(),
            ],

            'timeout'      => 10,
            'retries'      => 3,
            'retryDelayMs' => 1000,
        ];
    }
}
