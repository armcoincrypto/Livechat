<?php

namespace iEXPackages\Payment\Gateways\Payscrow;



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
        return (float)($this->data['order']['targetAmount'] ?? 0);
    }

    public function isFindPayment(): bool
    {
        return (bool)($this->data['success'] ?? false);
    }

    public function isRegisterTx(): bool{
        return false;
    }

    public function getStatus(): string
    {
        return (string)$this->data['order']['orderStatus'] ?? '';
    }


    public function isCancelled(): bool
    {
        return in_array($this->getStatus(), ['CanceledByAdmin', 'CanceledByTimeout', 'CanceledByTrader', 'CanceledByMerchant', 'CanceledByCustomer']);
    }

    public function isPending(): bool
    {
        return in_array($this->getStatus(), ['Unpaid', 'Processing', 'Queued', 'Paid']);
    }

    public function isSuccessful(): bool
    {
        return $this->getStatus() == 'Completed';
    }

    public function getStatusDescription(): string
    {
        if(in_array($this->getStatus(), ['Unpaid', 'Processing', 'Queued', 'Paid'])) {
            return 'Ожидается поступление на счет';
        }

        if(in_array($this->getStatus(), ['CanceledByAdmin', 'CanceledByTimeout', 'CanceledByTrader', 'CanceledByMerchant', 'CanceledByCustomer'])) {
            return 'Заявка отменена';
        }

        return 'Не определен';
    }

    public function rawData(): array
    {
        return $this->data;
    }
}


