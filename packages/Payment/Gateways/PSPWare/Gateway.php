<?php

namespace iEXPackages\Payment\Gateways\PSPWare;

use iEXPackages\Payment\Engines\AbstractGateway;
use iEXPackages\Payment\Engines\Message\AbstractRequest;

class Gateway extends AbstractGateway
{
    /**
     * Название Платежной системы
     */
    protected string $name = 'PSPWare';

    /**
     * Отправляем запрос на покупку
     */
    public function purchase(array $parameters = []): AbstractRequest
    {
        return $this->createRequest($this->getPurchaseRequest(), $parameters);
    }

    /**
     *  Получение данных от базовой версии Api
     */
    public function api()
    {
        return $this->apiService($this->getParameters());
    }
}
