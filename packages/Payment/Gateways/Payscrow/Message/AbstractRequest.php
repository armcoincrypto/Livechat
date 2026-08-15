<?php

namespace iEXPackages\Payment\Gateways\Payscrow\Message;

use iEXPackages\Payment\Engines\Message\AbstractRequest as BaseAbstractRequest;

abstract class AbstractRequest extends BaseAbstractRequest
{
    /**
     * Получить Api Key
     */
    public function getApiKey(): string
    {
        return $this->getParameter('api_key');
    }

    /**
     * Получить Api Key
     */
    public function getApiSecret(): string
    {
        return $this->getParameter('api_secret');
    }

    /**
     * Получить Api Key
     */
    public function getApiDomain(): string
    {
        return $this->getParameter('api_domain');
    }
}
