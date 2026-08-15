<?php

namespace iEXPackages\Payment\Gateways\Payeer\Message;

use iEXPackages\Payment\Engines\Message\AbstractRequest as BaseAbstractRequest;

abstract class AbstractRequest extends BaseAbstractRequest
{
    /**
     * Получить ID мерчанта
     */
    public function getMerchantId(): string
    {
        return $this->getParameter('merchant_id');
    }

    /**
     * Получить Секретный ключ
     */
    public function getSecretKey(): string
    {
        return $this->getParameter('secret_key');
    }

    /**
     * Подписать транзакцию
     */
    public function signatureTx(): string
    {
        return strtoupper(hash('sha256', implode(':', [
            $this->getMerchantId(),
            $this->getTransactionId(),
            $this->getAmount(),
            $this->getCurrency(),
            base64_encode($this->getDescription()),
            $this->getSecretKey(),
        ])));
    }
}
