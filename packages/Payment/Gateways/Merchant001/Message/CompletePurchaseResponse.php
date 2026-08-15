<?php

namespace iEXPackages\Payment\Gateways\Merchant001\Message;

use iEXPackages\Payment\Engines\Message\AbstractResponse;
use iEXPackages\Payment\Engines\Message\RequestInterface;
use iEXPackages\Payment\Exception\InvalidResponseException;

class CompletePurchaseResponse extends AbstractResponse
{
    /**
     * @var CompletePurchaseRequest|RequestInterface
     */
    protected RequestInterface $request;

    /**
     */
    public function __construct(RequestInterface $request, $data)
    {
        parent::__construct($request, $data);
    }

    /**
     * Если транзакция успешна
     */
    public function isSuccessful(): bool
    {
        return isset($this->data['status']) and in_array($this->data['status'], ['IN_PROGRESS', 'PAID', 'PENDING', 'CONFIRMED']);
    }

    /**
     * Если транзакция отменена
     */
    public function isCancelled(): bool
    {
        return isset($this->data['status']) and in_array($this->data['status'], ['FAILED', 'EXPIRED', 'CANCELED', 'CANCELED']);
    }

    /**
     * ID заявки от платежной системы
     *
     * @return string
     */
    public function getIdFromMerchant(): string
    {
        return $this->data['transaction']['id'];
    }

    /**
     * ID заявки (с сайта)
     */
    public function getTransactionId(): int
    {
        return (int)$this->data['invoiceId'];
    }
}
