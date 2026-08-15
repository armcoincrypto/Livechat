<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Abcex\Messages;

use iEXPackages\Payments\Core\Contracts\FetchPaymentResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPaymentResponse extends AbstractResponse  implements FetchPaymentResponseInterface
{
    private const string STATUS_SUCCESS = 'completed';

    private const array STATUSES_PENDING = [
        'processing'
    ];

    private const array STATUSES_CANCELLED = [
        'rejected', 'failed', 'canceled'
    ];

    public function exists(): bool
    {
        $id = $this->safeString($this->dataGet('id'));
        return $id !== null && $id !== '';
    }

    public function isRegisterPayment(): bool
    {
        return true;
    }

    /**
     * Найден ли объект платежа в системе (существует ли запись).
     */
    public function isFindPayment(): bool
    {
        $id = $this->safeString($this->dataGet('id'));
        return $id !== null && $id !== '';
    }

    /**
     * Статус транзакции в терминах B2BWallet.
     */
    public function getStatus(): ?string
    {
        return $this->safeString($this->dataGet('status'));
    }

    /**
     * Успешна ли операция.
     */
    public function isSuccessful(): bool
    {
        return $this->getStatus() === self::STATUS_SUCCESS;
    }

    /**
     * В ожидании ли операция.
     */
    public function isPending(): bool
    {
        return in_array($this->getStatus(), self::STATUSES_PENDING, true);
    }

    /**
     * Отменена ли операция.
     */
    public function isCancelled(): bool
    {
        return in_array($this->getStatus(), self::STATUSES_CANCELLED, true);
    }

    /**
     * Сумма платежа.
     */
    public function getAmount(): ?string
    {
        return $this->safeDecimal($this->dataGet('amount'));
    }

    /**
     * Если тебе нужно “валюта + сеть” отдельным методом — оставляем.
     */
    public function getCurrencyWithNetwork(): ?string
    {
        return $this->safeString($this->dataGet('networkId'));
    }

    /**
     * Внешний ID в системе шлюза.
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('id'));
    }

    /**
     * Внутренний orderId (если передавал).
     * Делает fallback: order_id -> label
     */
    public function getOrderId(): ?string
    {
        return $this->safeString($this->queryGet('order_id'))
            ?? $this->safeString($this->queryGet('label'));
    }

    /**
     * Текст ошибки.
     */
    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful() || $this->isPending()) {
            return null;
        }

        $msg = $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('error'))
            ?? $this->safeString($this->dataGet('error_message'));

        if ($msg !== null && $msg !== '') {
            return $msg;
        }

        $status = $this->getStatus();
        return $status ? ('Статус: ' . $status) : 'Не удалось определить статус платежа';
    }

    /**
     * Хэш транзакции (если отдаёт).
     */
    public function getTransactionHash(): ?string
    {
        return $this->safeString($this->dataGet('txId'));
    }

    /**
     * Описание статуса для UI.
     */
    public function getStatusDescription(): string
    {
        $status = $this->getStatus();

        if ($status === self::STATUS_SUCCESS) {
            return 'Транзакция успешно выполнена';
        }

        if (in_array($status, self::STATUSES_PENDING, true)) {
            return 'Транзакция в обработке, ожидайте';
        }

        if (in_array($status, self::STATUSES_CANCELLED, true)) {
            return match ($status) {
                'rejected' => 'Транзакция отклонена',
                'failed'   => 'Транзакция завершилась с ошибкой',
                'canceled' => 'Транзакция отменена',
                default    => 'Транзакция отменена',
            };
        }

        return 'Статус не определен';
    }
}
