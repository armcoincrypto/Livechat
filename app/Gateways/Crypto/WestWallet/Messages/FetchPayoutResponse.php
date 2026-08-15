<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\WestWallet\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPayoutResponse extends AbstractResponse
{
    private const STATUS_SUCCESS = 'completed';

    private const STATUSES_PENDING = [
        'pending',
        'sending',
    ];

    private const STATUSES_CANCELLED = [
        'network_error',
    ];

    public function getStatus(): ?string
    {
        return $this->safeString($this->dataGet('status'));
    }

    public function isSuccessful(): bool
    {
        return $this->getStatus() === self::STATUS_SUCCESS;
    }

    public function isPending(): bool
    {
        return in_array($this->getStatus(), self::STATUSES_PENDING, true);
    }

    public function isCancelled(): bool
    {
        return in_array($this->getStatus(), self::STATUSES_CANCELLED, true);
    }

    /**
     * tx_hash / номер транзакции.
     */
    public function getTransactionHash(): ?string
    {
        return $this->safeString($this->dataGet('blockchain_hash'));
    }

    /**
     * Адрес получателя.
     */
    public function getAddress(): ?string
    {
        return $this->safeString($this->dataGet('address'));
    }

    /**
     * Внешний ID выплаты в системе шлюза.
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('id'));
    }

    /**
     * Внутренний orderId (если ты передавал label в запросе).
     * Делаем fallback: query.order_id -> query.label -> data.label
     */
    public function getOrderId(): ?string
    {
        return $this->safeString($this->dataGet('label'));
    }

    /**
     * Сумма выплаты.
     */
    public function getAmount(): ?string
    {
        return $this->safeDecimal($this->dataGet('amount'));
    }

    /**
     * Сумма с учётом комиссии.
     * Если WestWallet не отдаёт отдельное поле — используем fallback amount.
     */
    public function getAmountWithFee(): ?string
    {
        return $this->getAmount();
    }
}
