<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\B2BWallet\Messages;

use iEXPackages\Payments\Core\Engine\AbstractRequest as BaseAbstractRequest;

abstract class AbstractRequest extends BaseAbstractRequest
{
    use Traits\GeneratedMerchantInputs,
        Traits\GeneratedPayInputs;

    protected function endpointStaticBaseUrl(): string
    {
        return 'https://b2bwallet.io';
    }

    /**
     * HTTP-настройки шлюза (только различия).
     * Отправка/логирование/ретраи реализованы в ядре (GatewayHttpClient + sendRequest()).
     */
    protected function httpConfig(): array
    {
        return [
            'baseUrl' => $this->endpointUrl('/'),
            'headers' => [
                'X-Api-Key' => $this->isMerchantContext() ?
                    $this->getApiKey() :
                    $this->getPayApiKey(),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => 10,
            'retries' => 3,
            'retryDelayMs' => 1000,
        ];
    }
}
