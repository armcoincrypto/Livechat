<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\AppexBit\Messages;

use iEXPackages\Payments\Core\Engine\AbstractRequest as BaseAbstractRequest;

/**
 * Базовый Request для шлюза AppexBit.
 *
 * Здесь:
 * - подключаются Generated*Inputs (позже генератором)
 * - задаётся базовый endpoint (можно статически или из конфига)
 * - создаётся HttpClient и единый callApi() для всех операций
 */
abstract class AbstractRequest extends BaseAbstractRequest
{
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
        return 'https://api.exgobit.net';
    }

    /**
     * HTTP конфиг для AppexBit.
     *
     * Особенности:
     * - заголовок x-api-key
     * - json/accept json
     */
    protected function httpConfig(): array
    {
        return [
            'baseUrl' => $this->endpointUrl('/'),
            'headers' => [
                'x-api-key' => $this->getApiKey(),
            ],
            'timeout' => 10,
            'retries' => 3,
            'retryDelayMs' => 1000,
        ];
    }
}
