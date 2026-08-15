<?php

namespace iEXPackages\Payment\Gateways\Volet\Message;

use iEXPackages\Payment\Engines\Message\ResponseInterface;
use iEXPackages\Payment\Exception\InvalidRequestException;
use iEXPackages\Payment\Exception\InvalidResponseException;

class CompletePurchaseRequest extends AbstractRequest
{
    /**
     * Получить массив необработанных данных для этого сообщения.
     * Формат этого варьируется от шлюза к шлюзу,
     * но обычно это либо ассоциативный массив, либо SimpleXMLElement.
     *
     * @throws InvalidRequestException
     */
    public function getData(): array
    {

        $this->validate(
            'ac_src_wallet', 'ac_transfer', 'ac_order_id'
        );

        return $this->parameters->all();
    }

    /**
     * Отправить запрос с указанными данными
     *
     * @return CompletePurchaseResponse
     *
     * @throws InvalidResponseException
     */
    public function sendData(array $data): ResponseInterface
    {
        return $this->response = new CompletePurchaseResponse($this, $data);
    }
}
