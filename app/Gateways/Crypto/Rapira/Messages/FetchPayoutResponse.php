<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Rapira\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPayoutResponse extends AbstractResponse
{
    public const STATUS_SUCCESS    = 'SUCCESS';
    public const STATUS_PENDING    = 'PENDING';
    public const STATUS_PROCESSING = 'PROCESSING';
    public const STATUS_CANCELED   = 'CANCELED';

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
        return in_array($this->getStatus(), [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    public function isCancelled(): bool
    {
        return $this->getStatus() === self::STATUS_CANCELED;
    }

    /**
     * tx_hash / номер транзакции (как у тебя transactionNumber)
     */
    public function getTransactionHash(): ?string
    {
        return $this->safeString($this->dataGet('transactionNumber'));
    }

    /**
     * Адрес получателя (полученный шлюзом).
     */
    public function getAddress(): ?string
    {
        return $this->safeString($this->dataGet('address'));
    }

    /**
     * Внешний ID выплаты (ID в системе Rapira).
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('id'));
    }

    /**
     * Сумма выплаты (totalAmount).
     */
    public function getAmount(): ?string
    {
        return $this->safeDecimal($this->dataGet('totalAmount'));
    }

    /**
     * Сумма дошедшая с учётом комиссии (arrivedAmount).
     */
    public function getAmountWithFee(): ?string
    {
        return $this->safeDecimal($this->dataGet('arrivedAmount'));
    }
}
