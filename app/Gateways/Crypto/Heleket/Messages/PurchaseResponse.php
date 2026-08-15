<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Heleket\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

/**
 * Ответ на создание платежа (incoming).
 */
final class PurchaseResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return (int)($this->dataGet('state') ?? 1) === 0
            && $this->safeString($this->dataGet('result.uuid')) !== null;
    }

    public function getAccountNumber(): ?string
    {
        return $this->safeString($this->dataGet('result.address'));
    }

    public function getCurrency(): ?string
    {
        return $this->safeString($this->dataGet('result.currency'));
    }

    public function getCurrencyWithNetwork(): ?string
    {
        return $this->safeString($this->dataGet('result.network'));
    }

    public function getAccountTag(): ?string
    {
        return $this->safeString($this->dataGet('result.comments'));
    }

    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('result.uuid'));
    }

    public function getStatus(): ?string
    {
        return $this->safeString($this->dataGet('result.status'))
            ?? $this->safeString($this->dataGet('result.payment_status'));
    }

    public function getOrderId()
    {
        return $this->safeString($this->dataGet('result.order_id'));
    }

    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful()) {
            return null;
        }

        return $this->safeString($this->dataGet('message'))
            ?? 'Heleket: не удалось получить адрес';
    }
}
