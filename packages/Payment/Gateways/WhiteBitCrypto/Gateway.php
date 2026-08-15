<?php

declare(strict_types=1);

namespace iEXPackages\Payment\Gateways\WhiteBitCrypto;

use iEXPackages\Payment\Engines\AbstractGateway;
use iEXPackages\Payment\Engines\Message\AbstractRequest;
use iEXPackages\Payment\Gateways\WhiteBitCrypto\Message\AddressRequest;

class Gateway extends AbstractGateway
{
    /**
     * Название Платежной системы
     */
    protected string $name = 'WhiteBitCrypto';

    /**
     * Отправляем запрос на покупку
     */
    public function purchase(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(AddressRequest::class, $parameters);
    }

    /**
     *  Получение данных от базовой версии Api
     */
    public function api(array $parameters = []): APIRequest
    {
        return $this->createApiRequest(APIRequest::class, $parameters);
    }
}
