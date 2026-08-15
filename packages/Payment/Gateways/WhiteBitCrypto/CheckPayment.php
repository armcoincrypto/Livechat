<?php

namespace iEXPackages\Payment\Gateways\WhiteBitCrypto;


class CheckPayment
{
    public function __construct(
        protected array $data
    ) { }

    /**
     * Получаем сумму
     *
     * @return float
     */
    public function getAmount(): float
    {
        return (float)($this->data['amount'] ?? 0);
    }

    public function getCurrency(): string
    {
        return (string)($this->data['ticker'] ?? '');
    }

    /**
     * Получаем ID Транзакции
     *
     * @return string
     */
    public function getTransactionHash(): string
    {
        return (string)($this->data['transactionHash'] ?? '');
    }

    /**
     * Получаем счетчик подтверждений
     *
     * @return int
     */
    public function getTransactionConfirmation(): int
    {
        return (int)($this->data['confirmations'] ?? 0);
    }

    public function isFindPayment(): bool
    {
        return (bool)($this->data['currency'] ?? false);
    }

    public function isRegisterTx(): bool
    {
        return true;
    }

    public function getStatus(): string
    {
        return $this->data['status'] ?? '';
    }


    public function isCancelled(): bool
    {
        return in_array($this->getStatus(), [4, 9]);
    }

    public function isPending(): bool
    {
        return (bool) in_array($this->getStatus(), [15, 5]);
    }

    public function isSuccessful(): bool
    {
        return in_array($this->getStatus(), [3, 7]);
    }

    public function getStatusDescription(): string
    {
        if($this->getStatus() == 'pending') {
            return 'Транзакция найдена в сети Blockchain, ожидайте подтверждений';
        }

        if($this->getStatus() == 'sending') {
            return 'Транзакция в процессе обработки...';
        }

        if($this->getStatus() == 'confirm_check') {
            return 'Транзакция в процессе обработки...';
        }

        if(in_array($this->getStatus(), ['network_error'])) {
            return 'Заявка отменена';
        }

        return 'Не определен';
    }

    public function rawData(): array
    {
        return $this->data;
    }
}

