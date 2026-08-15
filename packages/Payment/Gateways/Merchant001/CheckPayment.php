<?php

namespace iEXPackages\Payment\Gateways\Merchant001;


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
        return (float)($this->data['transaction']['pricing']['local']['amount'] ?? 0);
    }

    public function isFindPayment(): bool
    {
        return (bool)($this->data['status'] ?? false);
    }

    public function isRegisterTx(): bool{
        return false;
    }

    public function getStatus(): string
    {
        return (string)$this->data['status'] ?? '';
    }


    public function isCancelled(): bool
    {
        return in_array($this->getStatus(), ['FAILED', 'EXPIRED', 'CANCELED']);
    }

    public function isPending(): bool
    {
        return (bool) in_array($this->getStatus(), ['CREATED', 'PENDING', 'IN_PROGRESS', 'PAID']);
    }

    public function isSuccessful(): bool
    {
        return in_array($this->getStatus(), ['CONFIRMED']);
    }

    public function getStatusDescription(): string
    {
        if(in_array($this->getStatus(), ['CREATED', 'PENDING', 'IN_PROGRESS', 'PAID'])) {
            return 'Ожидается поступление на счет';
        }

        if(in_array($this->getStatus(), ['FAILED', 'EXPIRED', 'CANCELED'])) {
            return 'Заявка отменена';
        }

        return 'Не определен';
    }

    public function rawData(): array
    {
        return $this->data;
    }
}


