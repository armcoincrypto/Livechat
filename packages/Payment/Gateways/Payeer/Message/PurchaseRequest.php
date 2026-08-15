<?php

namespace iEXPackages\Payment\Gateways\Payeer\Message;

use iEXPackages\Payment\Engines\Message\ResponseInterface;
use iEXPackages\Payment\Exception\InvalidRequestException;

class PurchaseRequest extends AbstractRequest
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
        $this->validate('transactionId', 'currency', 'amount', 'description');

        return [
            'm_shop' => $this->getMerchantId(),
            'm_orderid' => $this->getTransactionId(),
            'm_amount' => $this->getAmount(),
            'm_curr' => $this->getCurrency(),
            'm_desc' => base64_encode($this->getDescription()),
            'm_sign' => $this->signatureTx(),
            'success_url' => $this->getReturnUrl(),
            'fail_url' => $this->getCancelUrl(),
        ];
    }

    /**
     * Отправить запрос с указанными данными
     */
    public function sendData(array $data): ResponseInterface
    {
        return $this->response = new PurchaseResponse($this, $data);
    }
}
