<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\PayCore\Messages;

use iEXPackages\Payments\Core\Engine\AbstractRequest as BaseAbstractRequest;
use App\Gateways\Fiat\PayCore\Services\HttpClient;

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
        return 'https://paycore.pw';
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
                'Accept' => 'application/json',
            ],

            'options' => [
                'query' => ['transaction_id' => $this->getTransactionId()]
            ],
            'timeout' => 20,
        ];
    }
}
