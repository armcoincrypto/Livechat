<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Abcex\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPayoutResponse extends AbstractResponse
{
    public function getStatus(): ?string
    {
        return $this->safeString($this->dataGet('status'));
    }

    public function isSuccessful(): bool
    {
        return $this->getStatus() === 'completed';
    }

    public function isPending(): bool
    {
        return in_array($this->getStatus(), ['submitted', 'processing'], true);
    }

    public function isCancelled(): bool
    {
        return $this->getStatus() === ['rejected', 'failed'];
    }

    /**
     * tx_hash / номер транзакции (как у тебя transactionNumber)
     */
    public function getTransactionHash(): ?string
    {
        return $this->safeString($this->dataGet('txId'));
    }

    /**
     * Адрес получателя (полученный шлюзом).
     */
    public function getAddress(): ?string
    {
        return $this->safeString($this->dataGet('address'));
    }

    /**
     * Сумма выплаты (totalAmount).
     */
    public function getAmount(): ?string
    {
        return $this->safeDecimal($this->dataGet('amount'));
    }
}
