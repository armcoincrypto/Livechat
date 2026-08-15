<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\B2BWallet\Messages;

use iEXPackages\Payments\Core\Contracts\PayoutTrackingResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPayoutResponse extends AbstractResponse
{

    public function getStatus(): ?string
    {
        return $this->safeString($this->dataGet('status'));
    }

    public function isSuccessful(): bool
    {
        return $this->getStatus() === 'confirmed';
    }

    public function isPending(): bool
    {
        return in_array($this->getStatus(), ['pending', 'confirmating', 'aml_checking', 'frozen'], true);
    }

    public function isCancelled(): bool
    {
        return $this->getStatus() === ['refunded', 'network_error'];
    }

    /**
     * tx_hash / номер транзакции (как у тебя transactionNumber)
     */
    public function getTransactionHash(): ?string
    {
        return $this->safeString($this->dataGet('tx_id'));
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
        return $this->safeDecimal($this->dataGet('amount'));
    }

    /**
     * Сумма дошедшая с учётом комиссии (arrivedAmount).
     */
    public function getAmountWithFee(): ?string
    {
        return $this->safeDecimal($this->dataGet('full_amount'));
    }
}
