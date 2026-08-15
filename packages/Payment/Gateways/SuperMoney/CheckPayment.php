<?php

namespace iEXPackages\Payment\Gateways\SuperMoney;


use Illuminate\Support\Str;

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
        return (string)($this->data['currency'] ?? '');
    }

    public function isFindPayment(): bool
    {
        return (bool)($this->data['id'] ?? false);
    }

    public function isRegisterTx(): bool
    {
        return true;
    }

    public function getStatus(): string
    {
        return Str::lower($this->data['status']) ?? '';
    }


    public function isCancelled(): bool
    {
        return in_array($this->getStatus(), ['cancel', 'decline', 'denied']);
    }

    public function isPending(): bool
    {
        return (bool) in_array($this->getStatus(), ['pending']);
    }

    public function isSuccessful(): bool
    {
        return in_array($this->getStatus(), ['success']);
    }

    public function getStatusDescription(): string
    {
        switch ($this->getStatus()) {
            case 'pending':
                return 'Платёж ожидает подтверждения или находится в обработке';
            case 'success':
                return 'Платёж успешно выполнен';
            case 'decline':
            case 'denied':
                return 'Платёж отклонён системой или банком';
            case 'cancel':
                return 'Платёж отменён пользователем или системой';
            default:
                return 'Неизвестный статус транзакции';
        }
    }

    public function rawData(): array
    {
        return $this->data;
    }
}

