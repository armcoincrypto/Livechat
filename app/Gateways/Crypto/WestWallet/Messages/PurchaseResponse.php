<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\WestWallet\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

/**
 * Ответ на создание входящего платежа (WestWallet).
 */
final class PurchaseResponse extends AbstractResponse
{
    /**
     * Платёж успешно создан, если API вернул error = "ok".
     */
    public function isSuccessful(): bool
    {
        return $this->safeString($this->dataGet('error')) === 'ok';
    }

    /**
     * Адрес для приёма средств.
     */
    public function getAccountNumber(): ?string
    {
        return $this->safeString($this->dataGet('address'));
    }

    /**
     * Валюта платежа.
     */
    public function getCurrency(): ?string
    {
        return $this->safeString($this->dataGet('currency'));
    }

    /**
     * Destination tag / memo (если используется).
     */
    public function getAccountTag(): ?string
    {
        return $this->safeString($this->dataGet('dest_tag'));
    }

    /**
     * Внешний идентификатор операции в системе WestWallet.
     *
     * Обычно это label, который ты передавал при создании.
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('label'));
    }
}
