<?php

namespace iEXPackages\Payment\Gateways\Volet\Message;

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
        return $this->data['ac_transaction_status'] == 'COMPLETED';
    }

    /**
     * Если транзакция отменена
     */
    public function isCancelled(): bool
    {
        return $this->data['ac_transfer'] == 0;
    }

    /**
     * ID заявки от платежной системы
     *
     * @return string
     */
    public function getTransferId(): string
    {
        return $this->data['ac_transfer'];
    }

    /**
     * ID заявки (с сайта)
     */
    public function getTransactionId(): int
    {
        return (int)$this->data['ac_order_id'];
    }

    /**
     * Получение суммы
     */
    public function getAmount(): float
    {
        return (float) $this->data['ac_buyer_amount_without_commission'];
    }

    /**
     * Получаем код валюты
     */
    public function getCurrency(): string
    {
        return str_replace('RUR', 'RUB', $this->data['ac_merchant_currency']);
    }

    /**
     * Получаем результат подписи с сервера ADVCash
     */
    public function getSign(): string
    {
        return $this->data['ac_hash'];
    }

    /**
     * Получаем номер счета отправителя
     */
    public function getFromAccount(): string
    {
        return $this->data['ac_src_wallet'];
    }

    /**
     * Получаем хэш по параметрам
     */
    private function signatureResponse(): string
    {
        return hash('sha256', implode(':', [
            $this->data['ac_transfer'],
            $this->data['ac_start_date'],
            $this->data['ac_sci_name'],
            $this->data['ac_src_wallet'],
            $this->data['ac_dest_wallet'],
            $this->data['ac_order_id'],
            $this->data['ac_amount'],
            $this->data['ac_merchant_currency'],
            $this->request->getSCIPassword(),
        ]));
    }
}
