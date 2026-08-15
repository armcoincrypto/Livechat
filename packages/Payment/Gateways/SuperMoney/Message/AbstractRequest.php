<?php

namespace iEXPackages\Payment\Gateways\SuperMoney\Message;

use iEXPackages\Payment\Engines\Message\AbstractRequest as BaseAbstractRequest;

abstract class AbstractRequest extends BaseAbstractRequest
{
    /**
     * Получить Api Key
     */
    public function getApiDomain(): string
    {
        return $this->getParameter('api_domain');
    }

    /**
     * Получить Api Key
     */
    public function getApiAuthToken(): string
    {
        return $this->getParameter('api_auth_token');
    }

    /**
     * Получить Api Key
     */
    public function getApiSignToken(): string
    {
        return $this->getParameter('api_sign_token');
    }
}
