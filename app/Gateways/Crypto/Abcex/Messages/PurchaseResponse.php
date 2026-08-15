<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Abcex\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

/**
 * Ответ на создание платежа (incoming).
 */
final class PurchaseResponse extends AbstractResponse
{
    /**
     * Успешно ли создан платёж.
     *
     * Для B2BWallet платёж считается созданным,
     * если API вернул address.
     */
    public function isSuccessful(): bool
    {
        return $this->safeString($this->dataGet('address')) !== null;
    }

    /**
     * Адрес (номер счёта) для приёма средств.
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
        return $this->safeString($this->dataGet('network'));
    }

    /**
     * Внешний идентификатор платежа.
     *
     * В B2BWallet используется поле "label".
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('address'));
    }

    /**
     * Унифицированный статус (опционально).
     */
    public function getStatus(): ?string
    {
        return $this->isSuccessful() ? 'success' : null;
    }
}
