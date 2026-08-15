<?php

namespace iEXPackages\Payment\Gateways\Merchant001\Message;

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
            'status', 'transaction'
        );

        return $this->httpRequest->request->all();
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
