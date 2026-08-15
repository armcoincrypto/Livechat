<?php

namespace iEXPackages\Payment\Gateways\Merchant001\Message;

use iEXPackages\Payment\Engines\Message\AbstractRequest as BaseAbstractRequest;

abstract class AbstractRequest extends BaseAbstractRequest
{
    /**
     * Конченый URL для запросов
     *
     * @return string
     */
    protected string $endpointUrl = 'https://api.merchant001.io';

    /**
     * Получить API Key
     */
    public function getApiToken(): string
    {
        return $this->getParameter('api_token');
    }
}
