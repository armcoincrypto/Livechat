<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Exnode\Messages;

use iEXPackages\Payments\Core\Contracts\BlockchainPaymentResponseInterface;
use iEXPackages\Payments\Core\Contracts\FetchPaymentResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPaymentResponse extends AbstractResponse implements FetchPaymentResponseInterface, BlockchainPaymentResponseInterface
{
    private const STATUS_SUCCESS  = 'SUCCESS';
    private const STATUS_PENDING  = 'ACCEPTED';
    private const STATUS_ERROR    = 'ERROR';

    /**
     * Можно ли ставить register_tx (если hash появился).
     */
    public function canRegisterTransaction(): bool
    {
        return $this->getTransactionHash() !== null && $this->getTransactionHash() !== '';
    }

    /**
     * Есть ли вообще запись транзакции у провайдера.
     *
     * В старой версии:
     *  - если нет transaction -> "Транзакция не найдена"
     */
    public function exists(): bool
    {
        $tx = $this->dataGet('transaction');

        return is_array($tx) && $tx !== [];
    }

    /**
     * Статус транзакции.
     */
    public function getStatus(): ?string
    {
        $status = $this->safeString($this->dataGet('transaction.status'));

        return $status !== null ? strtoupper($status) : null;
    }

    public function isSuccessful(): bool
    {
        return $this->exists() && $this->getStatus() === self::STATUS_SUCCESS;
    }

    public function isPending(): bool
    {
        return $this->exists() && $this->getStatus() === self::STATUS_PENDING;
    }

    public function isCancelled(): bool
    {
        return $this->exists() && $this->getStatus() === self::STATUS_ERROR;
    }

    /**
     * Сумма (лучше строкой, но у тебя исторически float).
     * Я оставлю string-версию и отдельный float-хелпер.
     */
    public function getAmount(): ?string
    {
        return $this->safeDecimal($this->dataGet('transaction.amount'));
    }

    public function getCurrency(): ?string
    {
        return $this->safeString($this->dataGet('transaction.token'));
    }

    public function getTransactionHash(): ?string
    {
        // В Exnode поле называется hash
        $hash = $this->safeString($this->dataGet('transaction.hash'));

        return $hash !== '' ? $hash : null;
    }

    /**
     * Внешний ID — если нужно.
     * Обычно у Exnode он не в transaction, а tracker_id приходит в query.
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->queryGet('tracker_id'));
    }

    public function getErrorMessage(): ?string
    {
        // 1) транзакции нет
        if (!$this->exists()) {
            return 'Транзакция не найдена';
        }

        // 2) транзакция есть, но hash пустой -> "Средства не поступили"
        if ($this->getTransactionHash() === null) {
            // не считаем это ошибкой, если pending — это норм
            if ($this->isPending()) {
                return null;
            }

            return 'Средства не поступили';
        }

        // 3) явная ошибка
        if ($this->isCancelled()) {
            return $this->safeString($this->dataGet('message'))
                ?? $this->safeString($this->dataGet('error'))
                ?? 'Заявка отменена';
        }

        return null;
    }

    public function getStatusDescription(): string
    {
        if (!$this->exists()) {
            return 'Транзакция не найдена';
        }

        // В старой версии: если нет hash → "Средства не поступили"
        if ($this->getTransactionHash() === null && !$this->isPending()) {
            return 'Средства не поступили';
        }

        return match ($this->getStatus()) {
            self::STATUS_PENDING => 'Транзакция в процессе обработки...',
            self::STATUS_ERROR   => 'Заявка отменена',
            self::STATUS_SUCCESS => 'Транзакция успешно выполнена',
            default              => 'Статус не определен',
        };
    }
}
