<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\ObmenkaClub\Messages;

use iEXPackages\Payments\Core\Engine\AbstractRequest as BaseAbstractRequest;

/**
 * Базовый Request для шлюза Obmenkaclub.
 *
 * Здесь:
 * - подключаются Generated*Inputs (позже генератором)
 * - задаётся базовый endpoint (можно статически или из конфига)
 * - создаётся HttpClient и единый callApi() для всех операций
 */
abstract class AbstractRequest extends BaseAbstractRequest
{
    // Пример подключения (когда сгенерируешь):
    use Traits\GeneratedPayInputs;

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
        return 'https://api.obmenka.club';
    }

    /**
     * HTTP-конфигурация ObmenkaClub.
     *
     * Особенности:
     * - авторизация через Bearer token (withToken)
     * - заголовки можно не указывать, token обработает GatewayHttpClient
     */
    protected function httpConfig(): array
    {
        return [
            'baseUrl' => $this->endpointUrl('/'),
            'token'   => $this->getPayApiKey(),

            'headers' => [
                'Accept' => 'application/json',
            ],

            'timeout'      => 10,
            'retries'      => 3,
            'retryDelayMs' => 1000,
        ];
    }
}
