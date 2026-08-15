<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Fiatcut\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class PurchaseResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        $success = $this->dataGet('success');

        if ($success !== true) {
            return false;
        }

        $orderId = $this->safeString($this->dataGet('data.order_id'));

        return $orderId !== null && $orderId !== '';
    }

    /**
     * Основные реквизиты (detail).
     */
    public function getAccountNumber(): ?string
    {
        return $this->safeString($this->dataGet('data.payment_detail.detail'));
    }

    /**
     * Доп. данные/инициалы (если нужно).
     */
    public function getAccountTag(): ?string
    {
        return $this->safeString($this->dataGet('data.payment_detail.initials'));
    }

    public function getCurrency(): ?string
    {
        return $this->safeString($this->dataGet('data.currency'));
    }

    /**
     * Внешний ID операции в системе FiatCut.
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('data.order_id'));
    }

    /**
     * Сумма к оплате (строкой, чтобы не терять точность).
     */
    public function getAmount(): ?string
    {
        return $this->safeDecimal($this->dataGet('data.amount'));
    }
    /**
     * (Опционально) Унифицированный статус.
     */
    public function getStatus(): ?string
    {
        return $this->isSuccessful() ? 'success' : null;
    }
}
