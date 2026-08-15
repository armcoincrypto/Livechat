<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\AlfabitPay\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

/**
 * Ответ на создание платежа (incoming) для AlfabitPay.
 */
final class PurchaseResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        $uid = $this->safeString($this->dataGet('data.uid'));
        return $uid !== null && $uid !== '';
    }

    /**
     * Реквизиты для оплаты (адрес/счет/кошелек).
     */
    public function getAccountNumber(): ?string
    {
        return $this->safeString($this->dataGet('data.requisites'));
    }

    /**
     * Memo / Destination Tag / комментарий (если нужен).
     */
    public function getAccountTag(): ?string
    {
        return $this->safeString($this->dataGet('data.requisitesMemoTag'));
    }

    /**
     * Валюта входящего платежа (если API возвращает).
     */
    public function getCurrency(): ?string
    {
        return $this->safeString($this->dataGet('data.currencyInCode'));
    }

    /**
     * Внешний идентификатор платежа (UID в системе AlfabitPay).
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('data.uid'));
    }

    /**
     * Унифицированный статус.
     */
    public function getStatus(): ?string
    {
        return $this->isSuccessful() ? 'success' : null;
    }

    /**
     * Сообщение об ошибке (если неуспех).
     */
    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful()) {
            return null;
        }

        return $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('error'))
            ?? $this->safeString($this->dataGet('error_message'));
    }
}
