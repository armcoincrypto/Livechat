<?php

declare(strict_types=1);

namespace iEXPackages\Payment\Gateways\WhiteBitCrypto\Message;

use iEXPackages\Payment\Engines\Message\AbstractResponse;
use iEXPackages\Payment\Engines\Message\RedirectResponseInterface;

class AddressResponse extends AbstractResponse implements RedirectResponseInterface
{
    public function isSuccessful(): bool
    {
        return isset($this->data['account']['address']);
    }

    public function isRedirect(): bool
    {
        return false;
    }

    /**
     * Получаем адрес
     */
    public function getAddress(): string
    {
        return $this->data['account']['address'];
    }

    /**
     * Получаем тэг
     */
    public function getTag(): string
    {
        return $this->data['account']['memo'] ?? '';
    }

    public function getIdFromMerchant(): string
    {
        return implode('__', [$this->getAddress(), $this->getTag()]);
    }
}
