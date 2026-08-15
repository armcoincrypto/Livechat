<?php

declare(strict_types=1);

namespace iEXPackages\Payment\Gateways\WhiteBitCrypto\Message;

use iEXPackages\Payment\Engines\Message\ResponseInterface;

class CompletePurchaseRequest extends AbstractRequest
{
    /**
     * Получить массив необработанных данных для этого сообщения.
     * Формат этого варьируется от шлюза к шлюзу,
     * но обычно это либо ассоциативный массив, либо SimpleXMLElement.
     *
     * @throws \iEXPackages\Payment\Exception\InvalidRequestException
     */
    public function getData(): array
    {
        $this->validate(
            'public_key', 'secret_key'
        );

        return $this->httpRequest->request->all();
    }

    /**
     * Отправить запрос с указанными данными
     *
     * @return CompletePurchaseResponse
     */
    public function sendData(array $data): ResponseInterface
    {
        return $this->response = new CompletePurchaseResponse($this, $data);
    }
}
