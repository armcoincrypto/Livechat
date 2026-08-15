<?php

namespace iEXPackages\Payment\Gateways\SuperMoney\Message;


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
        $method = strtolower($this->data['paymentMethod'] ?? '');

        if ($method === 'card' && isset($this->data['id'], $this->data['cardNumber'])) {
            return true;
        }

        if ($method === 'sbp' && isset($this->data['id'], $this->data['phoneNumber'])) {
            return true;
        }

        return false;
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
        $method = strtolower($this->data['paymentMethod'] ?? '');

        if ($method === 'card' && !empty($this->data['cardNumber'])) {
            return $this->data['cardNumber'];
        }

        if ($method === 'sbp' && !empty($this->data['phoneNumber'])) {
            return $this->data['phoneNumber'];
        }

        return '';
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
        return $this->data['id'];
    }

    /**
     * Получаем тэг
     */
    public function getTag(): string
    {
        return $this->data['owner'] ?? '';
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
