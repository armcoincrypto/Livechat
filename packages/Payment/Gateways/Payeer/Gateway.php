<?php

namespace iEXPackages\Payment\Gateways\Payeer;

use iEXPackages\Payment\Engines\AbstractGateway;
use iEXPackages\Payment\Engines\Message\AbstractRequest;

class Gateway extends AbstractGateway
{
    /**
     * Название Платежной системы
     */
    protected string $name = 'Payeer';

    /**
     * Отправляем запрос на покупку
     */
    public function purchase(array $parameters = []): AbstractRequest
    {
        return $this->createRequest($this->getPurchaseRequest(), $parameters);
    }

    /**
     * Обработка данных, после ответа от платежного шлюза
     */
    public function completePurchase(array $parameters = []): AbstractRequest
    {
        return $this->createRequest($this->getCompletePurchase(), $parameters);
    }

    /**
     *  Получение данных от базовой версии Api
     *
     * @return APIRequest
     */
    public function api()
    {
        return new APIRequest($this->getParameters());
    }
}
