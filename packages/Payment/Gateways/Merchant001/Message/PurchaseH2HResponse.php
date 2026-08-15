<?php

namespace iEXPackages\Payment\Gateways\Merchant001\Message;

use iEXPackages\Payment\Engines\Message\AbstractResponse;
use iEXPackages\Payment\Engines\Message\RedirectResponseInterface;
use iEXPackages\Payment\Engines\Message\RequestInterface;

class PurchaseH2HResponse extends AbstractResponse implements RedirectResponseInterface
{
    public function __construct(RequestInterface $request, $data)
    {
        parent::__construct($request, $data);
    }

    public function isSuccessful(): bool
    {
        return isset($this->data['requisite']);
    }

    public function isRedirect(): bool
    {
        return false;
    }

    public function getRedirectMethod(): string
    {
        return 'GET';
    }

    /**
     * Получаем адрес
     */
    public function getAddress(): string
    {
        return $this->data['requisite']['maskedAccountNumber'];
    }

    /**
     * Получаем валюту
     */
    public function getCurrency(): string
    {
        return $this->data['transaction']['pricing']['local']['currency'];
    }

    /**
     * Получаем описание
     */
    public function getLabel(): string
    {
        return $this->data['transaction']['id'];
    }

    /**
     * Получаем тэг
     */
    public function getTag(): string
    {
        return $this->data['requisite']['accountName'] ?? '';
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

    public function getBankName(): string
    {
        return $this->data['requisite']['method'] ?? '';
    }
}
