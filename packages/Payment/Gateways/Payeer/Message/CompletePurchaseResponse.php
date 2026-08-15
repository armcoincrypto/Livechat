<?php

namespace iEXPackages\Payment\Gateways\Payeer\Message;

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
     * @throws InvalidResponseException
     */
    public function __construct(RequestInterface $request, $data)
    {
        parent::__construct($request, $data);

        // Проверяем, если подписи не совместимы.
        if ($this->getSign() !== $this->signatureResponse()) {
            throw new InvalidResponseException('Хэш обратного вызова не соответствует ожидаемому значению');
        }
    }

    /**
     * Если транзакция успешна
     */
    public function isSuccessful(): bool
    {
        return $this->data['m_status'] == 'success';
    }

    /**
     * Если транзакция отменена
     */
    public function isCancelled(): bool
    {
        return $this->data['m_status'] != 'success';
    }

    /**
     * ID заявки от платежной системы
     *
     * @return string|int
     */
    public function getTransferId()
    {
        return $this->data['m_operation_id'];
    }

    /**
     * ID заявки (с сайта)
     */
    public function getTransactionId(): mixed
    {
        return $this->data['m_orderid'];
    }

    /**
     * Получение суммы
     *
     * @return string | float
     */
    public function getAmount()
    {
        return $this->data['m_amount'];
    }

    /**
     * Получаем код валюты
     */
    public function getCurrency(): string
    {
        return $this->data['m_curr'];
    }

    /**
     * Получаем результат подписи с сервера ADVCash
     */
    public function getSign(): string
    {
        return $this->data['m_sign'];
    }

    /**
     * Получаем номер счета отправителя
     */
    public function getFromAccount(): string
    {
        return $this->data['client_account'];
    }

    /**
     * Получаем хэш по параметрам
     */
    private function signatureResponse(): string
    {
        return mb_strtoupper(hash('sha256', implode(':', [
            $this->data['m_operation_id'],
            $this->data['m_operation_ps'],
            $this->data['m_operation_date'],
            $this->data['m_operation_pay_date'],
            $this->data['m_shop'],
            $this->data['m_orderid'],
            $this->data['m_amount'],
            $this->data['m_curr'],
            $this->data['m_desc'],
            $this->data['m_status'],
            $this->request->getSecretKey(),
        ])));
    }
}
