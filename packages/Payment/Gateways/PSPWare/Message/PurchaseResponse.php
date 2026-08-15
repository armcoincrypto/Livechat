<?php

namespace iEXPackages\Payment\Gateways\PSPWare\Message;

use iEXPackages\Payment\Engines\Message\AbstractResponse;
use iEXPackages\Payment\Engines\Message\RedirectResponseInterface;
use iEXPackages\Payment\Engines\Message\RequestInterface;

class PurchaseResponse extends AbstractResponse implements RedirectResponseInterface
{
    public function __construct(RequestInterface $request, $data)
    {
        parent::__construct($request, $data);
    }

    public function isSuccessful(): bool
    {
        return isset($this->data['id'], $this->data['status']);
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
        return $this->data['card'] ?? '';
    }

    /**
     * Получаем валюту
     */
    public function getCurrency(): string
    {
        return $this->data['currency'];
    }

    /**
     * Получаем описание
     */
    public function getLabel(): string
    {
        return (string)$this->data['id'];
    }

    /**
     * Получаем тэг
     */
    public function getTag(): string
    {
        return $this->data['recipient'];
    }

    public function getIdFromMerchant(): string
    {
        return (string)$this->data['id'];
    }

    public function getBankName(): string
    {
        return $this->data['bankName'] ?? '';
    }
}
