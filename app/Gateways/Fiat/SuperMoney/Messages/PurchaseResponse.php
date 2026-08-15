<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\SuperMoney\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

/**
 * Ответ на создание входящего платежа (SuperMoney).
 */
final class PurchaseResponse extends AbstractResponse
{
    /**
     * Платёж успешно создан, если:
     * - есть id
     * - и есть реквизит в зависимости от метода (card/sbp)
     */
    public function isSuccessful(): bool
    {
        $method = strtolower((string) $this->safeString($this->dataGet('paymentMethod')));

        if ($method === 'card') {
            return $this->dataHas('id') && $this->dataHas('cardNumber');
        }

        if ($method === 'sbp') {
            return $this->dataHas('id') && $this->dataHas('phoneNumber');
        }

        return false;
    }

    /**
     * Номер карты или телефон (SBP).
     */
    public function getAccountNumber(): ?string
    {
        $method = strtolower((string) $this->safeString($this->dataGet('paymentMethod')));

        if ($method === 'card') {
            return $this->safeString($this->dataGet('cardNumber'));
        }

        if ($method === 'sbp') {
            return $this->safeString($this->dataGet('phoneNumber'));
        }

        return null;
    }

    /**
     * ФИО владельца / комментарий.
     */
    public function getAccountTag(): ?string
    {
        return $this->safeString($this->dataGet('owner'));
    }

    /**
     * Валюта операции.
     */
    public function getCurrency(): ?string
    {
        return $this->safeString($this->dataGet('currency'));
    }

    /**
     * Название банка.
     */
    public function getBankName(): ?string
    {
        return $this->safeString($this->dataGet('bankName'));
    }

    /**
     * Внешний ID платежа в системе SuperMoney.
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('id'));
    }

    /**
     * Унифицированный статус.
     */
    public function getStatus(): ?string
    {
        return $this->isSuccessful() ? 'success' : null;
    }

    /**
     * Сообщение об ошибке (если есть).
     */
    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful()) {
            return null;
        }

        return $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('error'))
            ?? 'Не удалось создать платёж';
    }
}
