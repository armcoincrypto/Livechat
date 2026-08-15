<?php

namespace iEXPackages\Payment\Gateways\Payscrow\Message;

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
        $response = $this->httpRequest->query->all();
        $this->parameters->add(
            array_intersect_key($response, array_flip((array) ['clientOrderID']))
        );

        $this->validate('clientOrderID');

        return $response;
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
