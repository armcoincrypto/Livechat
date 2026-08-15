<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Goldex\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPayoutResponse extends AbstractResponse
{
    // Статусы Goldex (как в старом коде)
    private const STATUS_CLOSED = 'CLOSED';

    private const array STATUSES_PENDING = [
        'ACTIVE',
        'WORK',
        'WAITING',
        'PROCESSING',
    ];

    private const array STATUSES_CANCELLED = [
        'PROBLEM',
        'NO_AMOUNT',
        'REJECTED',
        'CANCELED',
    ];

    /**
     * Статус выплаты из ответа API.
     * Старое: $tx['data']['status']
     */
    public function getStatus(): ?string
    {
        $status = $this->safeString($this->dataGet('data.status'));

        $status = $status !== null ? strtoupper(trim($status)) : null;

        return ($status !== null && $status !== '') ? $status : null;
    }

    /**
     * Если API не вернул data.status — в старом коде считали это отменой.
     */
    private function hasValidStatus(): bool
    {
        return $this->getStatus() !== null;
    }

    public function isSuccessful(): bool
    {
        return $this->getStatus() === self::STATUS_CLOSED;
    }

    public function isPending(): bool
    {
        $status = $this->getStatus();
        return $status !== null && in_array($status, self::STATUSES_PENDING, true);
    }

    public function isCancelled(): bool
    {
        // 1) если вообще нет статуса — считаем отменой (как было)
        if (!$this->hasValidStatus()) {
            return true;
        }

        // 2) если статус из списка отмен
        $status = $this->getStatus();
        return $status !== null && in_array($status, self::STATUSES_CANCELLED, true);
    }

    /**
     * ID выплаты для cron-трекинга.
     *
     * В старом ты вызывал findTransaction($transaction_id),
     * значит ключ трекинга = requestId (который ты получил при payout).
     *
     * Обычно это:
     * - data.requestId
     * - или requestId (fallback)
     */
    public function getWithdrawalId(): ?string
    {
        $id = $this->safeString($this->dataGet('data.requestId'))
            ?? $this->safeString($this->dataGet('requestId'));

        return ($id !== null && $id !== '') ? $id : null;
    }

    /**
     * Человекопонятное описание статуса (удобно для логов/админки).
     */
    public function getStatusDescription(): string
    {
        $status = $this->getStatus();

        if ($status === null) {
            return 'Статус выплаты не получен';
        }

        if ($status === self::STATUS_CLOSED) {
            return 'Выплата выполнена';
        }

        if (in_array($status, self::STATUSES_PENDING, true)) {
            return 'Выплата обрабатывается';
        }

        if (in_array($status, self::STATUSES_CANCELLED, true)) {
            return 'Выплата отменена или завершилась ошибкой';
        }

        return 'Неизвестный статус: ' . $status;
    }
}
