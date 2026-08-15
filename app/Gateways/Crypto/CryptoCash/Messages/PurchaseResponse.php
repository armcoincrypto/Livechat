<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\CryptoCash\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

/**
 * Ответ на создание входящего платежа (CryptoCash).
 */
final class PurchaseResponse extends AbstractResponse
{
    /**
     * Успех, если code === 200.
     */
    public function isSuccessful(): bool
    {
        $code = $this->dataGet('code');

        return is_numeric($code) && (int) $code === 200;
    }

    /**
     * Адрес для приёма средств.
     */
    public function getAccountNumber(): ?string
    {
        return $this->safeString($this->dataGet('data.item.address'));
    }

    /**
     * Memo / tag (если используется).
     */
    public function getAccountTag(): ?string
    {
        return $this->safeString($this->dataGet('data.item.memo'));
    }

    /**
     * Внешний ID операции в системе CryptoCash.
     */
    public function getExternalId(): ?string
    {
        return $this->getOrderId();
        //return $this->safeString($this->dataGet('data.item.id'));
    }

    public function getOrderId()
    {
        return $this->safeString($this->dataGet('data.item.externalId'));
    }

    /**
     * Валюта.
     *
     * ⚠️ CryptoCash в этом ответе не возвращает currency —
     * если она нужна, бери из Request / Task.
     */
    public function getCurrency(): ?string
    {
        return null;
    }

    /**
     * Унифицированный статус.
     */
    public function getStatus(): ?string
    {
        return $this->isSuccessful() ? 'success' : 'error';
    }
}
