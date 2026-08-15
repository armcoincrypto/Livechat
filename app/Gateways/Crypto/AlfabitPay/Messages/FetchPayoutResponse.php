<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\AlfabitPay\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPayoutResponse extends AbstractResponse
{
    public function getStatus(): ?string
    {
        return $this->data['data']['status'];
    }

    public function isSuccessful(): bool
    {
        return $this->data['data'] and !empty($this->data['data']['status']);
    }

    public function isPending(): bool
    {
        return in_array($this->getStatus(), ['inProgress', 'invoiceWaitPay'], true);
    }

    public function isCancelled(): bool
    {
        return $this->getStatus() === ['failed', 'failed'];
    }

    /**
     * tx_hash / номер транзакции (как у тебя transactionNumber)
     */
    public function getTransactionHash(): ?string
    {
        return $this->data['data']['txId'];
    }

    /**
     * Адрес получателя (полученный шлюзом).
     */
    public function getAddress(): ?string
    {
        return $this->data['data']['requisites'];
    }

    /**
     * Сумма выплаты (totalAmount).
     */
    public function getAmount(): ?string
    {
        return $this->data['data']['amountOutFact'];
    }
}
