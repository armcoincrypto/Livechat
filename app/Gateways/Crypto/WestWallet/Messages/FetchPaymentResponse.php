<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\WestWallet\Messages;

use iEXPackages\Payments\Core\Contracts\FetchPaymentResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPaymentResponse extends AbstractResponse implements FetchPaymentResponseInterface
{
    private const STATUS_SUCCESS = 'completed';

    private const STATUSES_PENDING = [
        'pending',
        'sending',
    ];

    private const STATUSES_CANCELLED = [
        'network_error',
    ];

    public function isRegisterPayment(): bool
    {
        return true;
    }

    public function exists(): bool
    {
        $id = $this->safeString($this->dataGet('id'));
        return $id !== null && $id !== '';
    }

    /**
     * Найдена ли транзакция (существует ли запись).
     */
    public function isFindPayment(): bool
    {
        $id = $this->safeString($this->dataGet('id'));
        return $id !== null && $id !== '';
    }

    /**
     * Статус транзакции.
     */
    public function getStatus(): ?string
    {
        return $this->safeString($this->dataGet('status'));
    }

    public function isSuccessful(): bool
    {
        return $this->isFindPayment() && $this->getStatus() === self::STATUS_SUCCESS;
    }

    public function isPending(): bool
    {
        return $this->isFindPayment()
            && in_array($this->getStatus(), self::STATUSES_PENDING, true);
    }

    public function isCancelled(): bool
    {
        return $this->isFindPayment()
            && in_array($this->getStatus(), self::STATUSES_CANCELLED, true);
    }

    /**
     * Сумма (строкой, чтобы не терять точность).
     */
    public function getAmount(): ?string
    {
        return $this->safeDecimal($this->dataGet('amount'));
    }

    public function getCurrencyWithNetwork(): ?string
    {
        return $this->safeString($this->dataGet('currency'));
    }

    /**
     * Хэш транзакции в блокчейне.
     */
    public function getTransactionHash(): ?string
    {
        return $this->safeString($this->dataGet('blockchain_hash'));
    }

    /**
     * Количество подтверждений.
     */
    public function getConfirmationsCurrent(): int
    {
        $v = $this->dataGet('blockchain_confirmations');
        return is_numeric($v) ? (int) $v : 0;
    }

    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful() || $this->isPending()) {
            return null;
        }

        if (!$this->isFindPayment()) {
            return 'Транзакция не найдена';
        }

        return $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('error'))
            ?? ('Статус: ' . ($this->getStatus() ?? 'unknown'));
    }

    public function getStatusDescription(): string
    {
        return match ($this->getStatus()) {
            'pending'       => 'Транзакция ожидает подтверждений',
            'sending'       => 'Транзакция отправляется в сеть',
            'completed'     => 'Транзакция успешно выполнена',
            'network_error' => 'Ошибка сети, транзакция отменена',
            default         => 'Статус транзакции не определен',
        };
    }

    public function getTransactionConfirmations(): array
    {
        return [
            'current' => $this->getConfirmationsCurrent(),
        ];
    }
}
