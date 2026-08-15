<?php

namespace iEXPackages\Payment\Gateways\PSPWare\Message;

use iEXPackages\Payment\Engines\Message\AbstractRequest as BaseAbstractRequest;

abstract class AbstractRequest extends BaseAbstractRequest
{

    protected string $baseUrl = 'https://api.pspware.space';

    /**
     * Получить Api Key
     */
    public function getApiKey(): string
    {
        return $this->getParameter('api_key');
    }

    public function getMerchantId(): string
    {
        return $this->getParameter('merchant_id');
    }
}
