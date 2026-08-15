<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Rapira\Messages;

use iEXPackages\Payments\Core\Contracts\BlockchainPaymentResponseInterface;
use iEXPackages\Payments\Core\Contracts\FetchPaymentResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPaymentResponse extends AbstractResponse  implements FetchPaymentResponseInterface, BlockchainPaymentResponseInterface
{
    public const STATUS_SUCCESS               = 'SUCCESS';
    public const STATUS_FAILED                = 'FAILED';
    public const STATUS_REJECTED              = 'REJECTED';
    public const STATUS_RETURNED              = 'RETURNED';
    public const STATUS_MEMPOOL               = 'MEMPOOL';
    public const STATUS_PENDING_CONFIRMATIONS = 'PENDING_CONFIRMATIONS';
    public const STATUS_PENDING_AML           = 'PENDING_AML';
    public const STATUS_MANUAL_CHECK          = 'MANUAL_CHECK';

    public function canRegisterTransaction(): bool
    {
        return true;
    }

    public function exists(): bool
    {
        $id = $this->safeInt($this->dataGet('id'));
        return $id !== null && $id > 0;
    }

    /**
     * Статус транзакции в терминах Rapira.
     */
    public function getStatus(): ?string
    {
        return $this->safeString($this->dataGet('status'));
    }

    /**
     * Успешна ли операция (в терминах модуля).
     * Успех = STATUS_SUCCESS.
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
        return in_array($this->getStatus(), [
            self::STATUS_MEMPOOL,
            self::STATUS_PENDING_CONFIRMATIONS,
            self::STATUS_PENDING_AML,
            self::STATUS_MANUAL_CHECK,
        ], true);
    }

    /**
     * Отменена ли операция.
     * Для Rapira это можно считать cancelled/failed-блоком (returned/rejected).
     */
    public function isCancelled(): bool
    {
        return in_array($this->getStatus(), [
            self::STATUS_REJECTED,
            self::STATUS_RETURNED,
        ], true);
    }

    /**
     * Если статус FAILED — это именно failure.
     * Но базовый AbstractResponse всё равно вернёт failure для всего, что не success/pending/cancelled.
     * Здесь можно уточнить явно:
     */
    public function isFailure(): bool
    {
        return in_array($this->getStatus(), [
                self::STATUS_FAILED,
            ], true) || parent::isFailure();
    }

    /**
     * Сумма (лучше string, чтобы не терять точность).
     */
    public function getAmount(): ?string
    {
        // Rapira отдаёт amount числом/строкой
        return $this->safeDecimal($this->dataGet('amount'));
    }

    /**
     * Валюта транзакции.
     * Rapira у тебя использует поле 'unit'.
     */
    public function getCurrency(): ?string
    {
        return $this->safeString($this->dataGet('unit'));
    }

    /**
     * Внешний ID в системе шлюза.
     * Обычно это 'id'.
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('id'));
    }

    /**
     * OrderId (если ты передавал его в query).
     */
    public function getOrderId(): ?string
    {
        return $this->safeString($this->queryGet('order_id'));
    }

    /**
     * Текст ошибки (если есть).
     */
    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful() || $this->isPending()) {
            return null;
        }

        // если Rapira отдаёт сообщение
        $msg = $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('error'))
            ?? $this->safeString($this->dataGet('error_message'));

        if ($msg !== null) {
            return $msg;
        }

        $status = $this->getStatus();
        return $status ? ('Статус: ' . $status) : 'Не удалось определить статус платежа';
    }

    /**
     * Хэш транзакции (txid).
     */
    public function getTransactionHash(): ?string
    {
        return $this->safeString($this->dataGet('txid'));
    }

    /**
     * Текущее число подтверждений.
     */
    public function getConfirmationsCurrent(): int
    {
        return (int) ($this->safeInt($this->dataGet('confirmations')) ?? 0);
    }

    /**
     * Требуемое число подтверждений.
     */
    public function getConfirmationsRequired(): int
    {
        return (int) ($this->safeInt($this->dataGet('requireConfirmations')) ?? 0);
    }

    /**
     * Нужно ли еще ждать подтверждений.
     */
    public function needsMoreConfirmations(): bool
    {
        $required = $this->getConfirmationsRequired();
        if ($required <= 0) {
            return false;
        }

        return $this->getConfirmationsCurrent() < $required;
    }

    /**
     * Описание статуса (как у тебя было).
     */
    public function getStatusDescription(): string
    {
        return match ($this->getStatus()) {
            self::STATUS_MEMPOOL,
            self::STATUS_PENDING_CONFIRMATIONS,
            self::STATUS_PENDING_AML,
            self::STATUS_MANUAL_CHECK => 'Платеж получен и ожидает подтверждения',

            self::STATUS_FAILED,
            self::STATUS_REJECTED,
            self::STATUS_RETURNED => 'Транзакция отменена или отклонена',

            self::STATUS_SUCCESS => 'Транзакция успешно выполнена',

            default => 'Статус транзакции не определен',
        };
    }

    /**
     * Удобный метод для UI.
     */
    public function getTransactionConfirmations(): array
    {
        return [
            'current'  => $this->getConfirmationsCurrent(),
            'required' => $this->getConfirmationsRequired(),
        ];
    }
}
