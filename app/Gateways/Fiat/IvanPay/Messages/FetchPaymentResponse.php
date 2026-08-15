<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\IvanPay\Messages;

use iEXPackages\Payments\Core\Contracts\FetchPaymentResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPaymentResponse extends AbstractResponse implements FetchPaymentResponseInterface
{
    private const STATUS_CREATED     = 'created';
    private const STATUS_IN_PROGRESS = 'in_progress';
    private const STATUS_COMPLETED   = 'completed';
    private const STATUS_FAILED      = 'failed';

    /**
     * Есть ли вообще объект платежа у провайдера (существует ли запись).
     *
     * Это НЕ равно success/pending — это лишь факт существования записи.
     */
    public function exists(): bool
    {
        $id = $this->dataGet('payment.id');

        return is_numeric($id) && (int) $id > 0;
    }

    /**
     * Статус в терминах IvanPay (нижний регистр).
     */
    public function getStatus(): ?string
    {
        $status = $this->safeString($this->dataGet('payment.status'));

        $status = $status !== null ? strtolower(trim($status)) : null;

        return $status !== '' ? $status : null;
    }

    public function isSuccessful(): bool
    {
        return $this->exists() && $this->getStatus() === self::STATUS_COMPLETED;
    }

    public function isPending(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        return in_array($this->getStatus(), [self::STATUS_CREATED, self::STATUS_IN_PROGRESS], true);
    }

    public function isCancelled(): bool
    {
        return $this->exists() && $this->getStatus() === self::STATUS_FAILED;
    }


    public function getAmount(): ?string
    {
        return $this->safeDecimal($this->dataGet('payment.amount'));
    }

    public function getCurrency(): ?string
    {
        return $this->safeString($this->dataGet('payment.currency'));
    }

    /**
     * Внешний ID в системе шлюза.
     */
    public function getExternalId(): ?string
    {
        $id = $this->dataGet('payment.id');

        return is_numeric($id) ? (string) $id : $this->safeString($id);
    }

    /**
     * Текст ошибки.
     */
    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful() || $this->isPending()) {
            return null;
        }

        // иногда API может отдавать error/message на верхнем уровне
        $msg = $this->safeString($this->dataGet('error'))
            ?? $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('error_message'));

        if ($msg !== null && $msg !== '') {
            return $msg;
        }

        return $this->getStatusDescription();
    }

    /**
     * Описание статуса для UI.
     */
    public function getStatusDescription(): string
    {
        return match ($this->getStatus()) {
            self::STATUS_CREATED     => 'Платёж создан, ожидается обработка',
            self::STATUS_IN_PROGRESS => 'Платёж в процессе обработки',
            self::STATUS_COMPLETED   => 'Платёж успешно завершён',
            self::STATUS_FAILED      => 'Платёж завершился ошибкой',
            default                  => $this->exists() ? 'Статус не определён' : 'Платёж не найден',
        };
    }
}
