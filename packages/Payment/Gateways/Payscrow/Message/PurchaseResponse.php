<?php

namespace iEXPackages\Payment\Gateways\Payscrow\Message;

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
        return isset($this->data['success']) && $this->data['success'] == 1;
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
        return mask_formatting_bank_card($this->data['holderAccount']);
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
        return $this->data['orderId'];
    }

    /**
     * Получаем тэг
     */
    public function getTag(): string
    {
        return $this->data['holderName'] ?? '';
    }

    public function getIdFromMerchant(): int
    {
        return (int)$this->data['orderId'];
    }
}
