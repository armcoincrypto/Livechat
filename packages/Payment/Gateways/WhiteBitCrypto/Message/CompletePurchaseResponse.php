<?php

declare(strict_types=1);

namespace iEXPackages\Payment\Gateways\WhiteBitCrypto\Message;

use iEXPackages\Payment\Engines\Message\AbstractResponse;
use iEXPackages\Payment\Engines\Message\RequestInterface;

class CompletePurchaseResponse extends AbstractResponse
{
    protected RequestInterface $request;

    public function __construct(RequestInterface $request, $data)
    {
        parent::__construct($request, $data);
    }

    /**
     * Если транзакция успешна
     */
    public function isSuccessful(): bool
    {
        return $this->data['status'] == 'pending';
    }

    /**
     * Если транзакция отменена
     */
    public function isCancelled(): bool
    {
        return $this->data['m_status'] != 'completed';
    }

    /**
     * ID заявки от платежной системы
     *
     * @return string|int
     */
    public function getTransferId()
    {
        return $this->data['id'];
    }

    /**
     * ID заявки (с сайта)
     *
     * @return string|int
     */
    public function getTransactionId(): mixed
    {
        return $this->data['label'];
    }

    /**
     * Получение суммы
     */
    public function getAmount(): float
    {
        return $this->data['amount'];
    }

    /**
     * Получаем код валюты
     */
    public function getCurrency(): string
    {
        return $this->data['currency'];
    }
}
