<?php

namespace iEXPackages\Payment\Gateways\PSPWare;

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
        return (float)($this->data['sum'] ?? 0);
    }

    public function isFindPayment(): bool
    {
        return isset($this->data['id']);
    }

    public function isRegisterTx(): bool{
        return false;
    }

    public function getStatus(): string
    {
        return strtolower((string)($this->data['status'] ?? ''));
    }

    public function isCancelled(): bool
    {
        return in_array($this->getStatus(), ['canceled', 'failed']);
    }

    public function isPending(): bool
    {
        return in_array($this->getStatus(), ['processing', 'appel']);
    }

    public function isSuccessful(): bool
    {
        return $this->getStatus() == 'success';
    }

    public function getStatusDescription(): string
    {
        if(in_array($this->getStatus(), ['processing', 'appel'])) {
            return 'Ожидается поступление на счет';
        }

        if(in_array($this->getStatus(), ['canceled', 'failed'])) {
            return 'Заявка отменена';
        }

        return 'Не определен';
    }

    public function rawData(): array
    {
        return $this->data;
    }
}


