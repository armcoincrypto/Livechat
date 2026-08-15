<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\B2BWallet\Messages;

use iEXPackages\Payments\Core\Contracts\BlockchainPaymentResponseInterface;
use iEXPackages\Payments\Core\Contracts\FetchPaymentResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPaymentResponse extends AbstractResponse implements FetchPaymentResponseInterface, BlockchainPaymentResponseInterface
{
    private const string STATUS_SUCCESS = 'confirmed';

    private const array STATUSES_PENDING = [
        'pending',
        'confirmating',
        'aml_checking',
        'frozen',
    ];

    private const array STATUSES_CANCELLED = [
        'refunded',
        'network_error',
    ];

    public function canRegisterTransaction(): bool
    {
        return true;
    }

    /**
     * Найден ли объект платежа в системе (существует ли запись).
     */
    public function exists(): bool
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
     * Валюта платежа.
     */
    public function getCurrency(): ?string
    {
        return $this->safeString($this->dataGet('coin'));
    }

    /**
     * Если тебе нужно “валюта + сеть” отдельным методом — оставляем.
     */
    public function getCurrencyWithNetwork(): ?string
    {
        return $this->getCurrency();
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
        return $this->safeString($this->dataGet('tx_id'));
    }

    /**
     * Описание статуса для UI.
     */
    public function getStatusDescription(): string
    {
        return match ($this->getStatus()) {
            'pending'       => 'Транзакция найдена в сети Blockchain, ожидайте подтверждений',
            'confirmating'  => 'Транзакция в процессе подтверждения...',
            'aml_checking'  => 'Проверка AML, ожидайте...',
            'frozen'        => 'Транзакция заморожена, ожидайте решения',
            'confirmed'     => 'Транзакция успешно подтверждена',
            'refunded'      => 'Транзакция возвращена',
            'network_error' => 'Заявка отменена (ошибка сети)',
            default         => 'Статус не определен',
        };
    }
}
