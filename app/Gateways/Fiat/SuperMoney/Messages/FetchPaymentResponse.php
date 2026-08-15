<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\SuperMoney\Messages;

use iEXPackages\Payments\Core\Contracts\FetchPaymentResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;
use Illuminate\Support\Str;

final class FetchPaymentResponse extends AbstractResponse implements FetchPaymentResponseInterface
{
    private const STATUSES_SUCCESS = ['success'];

    private const STATUSES_PENDING = ['pending'];

    private const STATUSES_CANCELLED = ['cancel', 'decline', 'denied'];

    public function exists(): bool
    {
        $id = $this->safeString($this->dataGet('id'));
        if ($id === null || $id === '') {
            return false;
        }

        // В ответе поле extId = идентификатор твоей заявки (у тебя extId = 1000)
        $extId = $this->safeString($this->dataGet('extId'));
        if ($extId === null || $extId === '') {
            return false;
        }

        // Приоритет: task.id, затем query.order_id/label (если task не передали)
        $taskId  = $this->getTask() ? (string) $this->getTask()->id : null;
        $orderId = $taskId;

        if ($orderId === null || $orderId === '') {
            return false;
        }

        return (int) $extId === (int) $orderId;
    }

    /**
     * Транзакция считается найденной и “нашей”, если:
     * - есть id
     * - extId совпадает с task.id (или query.order_id)
     */
    public function isFindPayment(): bool
    {
        $id = $this->safeString($this->dataGet('id'));
        if ($id === null || $id === '') {
            return false;
        }

        // В ответе поле extId = идентификатор твоей заявки (у тебя extId = 1000)
        $extId = $this->safeString($this->dataGet('extId'));
        if ($extId === null || $extId === '') {
            return false;
        }

        // Приоритет: task.id, затем query.order_id/label (если task не передали)
        $taskId  = $this->getTask() ? (string) $this->getTask()->id : null;
        $orderId = $taskId;

        if ($orderId === null || $orderId === '') {
            return false;
        }

        return (int) $extId === (int) $orderId;
    }

    public function getStatus(): ?string
    {
        $status = $this->safeString($this->dataGet('status'));
        return $status !== null ? Str::lower($status) : null;
    }


    /**
     * Сумма платежа (строкой, чтобы не терять точность).
     */
    public function getAmount(): ?string
    {
        return $this->safeDecimal($this->dataGet('amount'));
    }

    public function getCurrency(): ?string
    {
        return $this->safeString($this->dataGet('currency'));
    }

    public function isSuccessful(): bool
    {
        return $this->isFindPayment()
            && in_array($this->getStatus(), self::STATUSES_SUCCESS, true);
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

    public function getExternalId(): ?string
    {
        // Внешний id у провайдера
        return $this->safeString($this->dataGet('id'));
    }

    public function getErrorMessage(): ?string
    {
        if (!$this->isFindPayment()) {
            return 'Транзакция не найдена';
        }

        if ($this->isCancelled()) {
            return $this->safeString($this->dataGet('message'))
                ?? $this->safeString($this->dataGet('error'))
                ?? 'Транзакция отменена';
        }

        return null;
    }

    public function getStatusDescription(): string
    {
        return match ($this->getStatus()) {
            'pending', 'processing' =>
            'Платёж ожидает подтверждения или находится в обработке',

            'success', 'paid', 'confirmed' =>
            'Платёж успешно выполнен',

            'decline', 'denied' =>
            'Платёж отклонён системой или банком',

            'cancel', 'canceled' =>
            'Платёж отменён пользователем или системой',

            default =>
            'Неизвестный статус транзакции',
        };
    }
}
