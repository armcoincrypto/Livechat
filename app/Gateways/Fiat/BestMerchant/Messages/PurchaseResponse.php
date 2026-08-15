<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\BestMerchant\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

/**
 * Ответ на создание платежа (incoming) для BestMerchant.
 */
final class PurchaseResponse extends AbstractResponse
{
    /**
     * Платёж считается успешно созданным,
     * если API вернул внешний идентификатор (id).
     */
    public function isSuccessful(): bool
    {
        return $this->safeString($this->dataGet('id')) !== null;
    }

    /**
     * Реквизиты для приёма средств (адрес / счёт).
     */
    public function getAccountNumber(): ?string
    {
        return $this->safeString($this->dataGet('requisites'));
    }

    /**
     * Название банка / платёжного метода (если применимо).
     */
    public function getBankName(): ?string
    {
        return $this->safeString($this->dataGet('bankName'));
    }

    /**
     * Дополнительное описание реквизитов (назначение, комментарий и т.п.).
     */
    public function getAccountTag(): ?string
    {
        return $this->safeString($this->dataGet('bankDescription'));
    }

    /**
     * Валюта платежа.
     */
    public function getCurrency(): ?string
    {
        return $this->safeString($this->dataGet('currency'));
    }

    /**
     * Внешний идентификатор платежа в системе шлюза.
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('id'));
    }

    /**
     * Унифицированный статус операции.
     *
     * Для PurchaseResponse:
     *  - success означает, что реквизиты успешно созданы.
     */
    public function getStatus(): ?string
    {
        return $this->isSuccessful() ? 'success' : null;
    }

    /**
     * Сообщение об ошибке (если операция неуспешна).
     */
    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful()) {
            return null;
        }

        return $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('error'))
            ?? $this->safeString($this->dataGet('error_message'))
            ?? 'Не удалось создать платёж';
    }
}
