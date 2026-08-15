<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Firekassa\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPayoutResponse extends AbstractResponse
{
    // Firekassa статусы (строковые)
    protected const STATUSES_SUCCESS = [
        'paid',
        'overpaid', // если вдруг отдаёт при переплате
    ];

    protected const STATUSES_PENDING = [
        'process',
        'waiting',
        'partially-paid',
    ];

    protected const STATUSES_CANCELLED = [
        'error',
        'cancel',
        'expired',
    ];

    /**
     * Статус выплаты (строкой, lower-case).
     *
     * Firekassa обычно возвращает:
     *   status => process|paid|error|cancel|expired|...
     */
    public function getStatus(): ?string
    {
        $status = $this->safeString($this->dataGet('status'));
        $status = $status !== null ? strtolower(trim($status)) : null;

        return ($status !== null && $status !== '') ? $status : null;
    }

    public function isSuccessful(): bool
    {
        $status = $this->getStatus();
        return $status !== null && in_array($status, self::STATUSES_SUCCESS, true);
    }

    public function isPending(): bool
    {
        $status = $this->getStatus();
        return $status !== null && in_array($status, self::STATUSES_PENDING, true);
    }

    public function isCancelled(): bool
    {
        $status = $this->getStatus();
        return $status !== null && in_array($status, self::STATUSES_CANCELLED, true);
    }

    /**
     * ID выплаты для cron-трекинга.
     *
     * Для Firekassa это всегда "id".
     * Если id нет — можно сделать fallback на query.externalId (если ты его отправляешь).
     */
    public function getWithdrawalId(): ?string
    {
        $paymentId = $this->safeString($this->dataGet('id'));
        if ($paymentId !== null && $paymentId !== '') {
            return $paymentId;
        }

        // fallback: то, что передавали в fetchPayout
        return $this->safeString($this->queryGet('externalId'))
            ?? $this->safeString($this->queryGet('id'));
    }

    /**
     * Удобное описание статуса для UI/логов.
     */
    public function getStatusDescription(): string
    {
        return match ($this->getStatus()) {
            'process'        => 'Выплата в процессе',
            'waiting'        => 'Ожидает обработки',
            'partially-paid' => 'Частично выполнено (в процессе)',
            'paid'           => 'Выплата выполнена',
            'overpaid'       => 'Выплата выполнена (переплата)',
            'cancel'         => 'Выплата отменена',
            'expired'        => 'Выплата истекла',
            'error'          => 'Ошибка выплаты',
            default          => 'Статус не определен',
        };
    }

    /**
     * Текст ошибки, если статус failed/cancelled.
     */
    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful() || $this->isPending()) {
            return null;
        }

        return $this->safeString($this->dataGet('payment_error'))
            ?? $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('error'))
            ?? $this->getStatusDescription();
    }
}
