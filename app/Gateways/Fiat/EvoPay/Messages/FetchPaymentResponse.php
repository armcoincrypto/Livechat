<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\EvoPay\Messages;

use iEXPackages\Payments\Core\Contracts\FetchPaymentResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPaymentResponse extends AbstractResponse implements FetchPaymentResponseInterface
{
    // BestMerchant: числовые статусы
    private const STATUS_WAIT_CLIENT_CONFIRM = -1;
    private const STATUS_WAIT_PAY            = 0;
    private const STATUS_CONFIRMED           = 1;
    private const STATUS_CANCELLED           = 2;


    public function exists(): bool
    {
        $id = $this->safeString($this->dataGet('id'));
        return $id !== null && $id !== '';
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
     * Статус заявки (как строка, чтобы API было единообразным).
     * Возвращаем '-1', '0', '1', '2' или null.
     */
    public function getStatus(): ?string
    {
        // status может быть int или строкой
        $raw = $this->dataGet('status');

        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_int($raw)) {
            return (string) $raw;
        }

        if (is_string($raw) && $raw !== '') {
            return $raw;
        }

        return null;
    }

    /**
     * Внутренний helper: привести status к int или null.
     */
    private function statusInt(): ?int
    {
        $raw = $this->dataGet('status');

        if (is_int($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '' && is_numeric($raw)) {
            return (int) $raw;
        }

        return null;
    }

    /**
     * Успешно ли подтверждена заявка.
     */
    public function isSuccessful(): bool
    {
        return $this->statusInt() === self::STATUS_CONFIRMED;
    }

    /**
     * В ожидании ли (ожидает подтверждения клиента или оплаты).
     */
    public function isPending(): bool
    {
        return in_array($this->statusInt(), [
            self::STATUS_WAIT_CLIENT_CONFIRM,
            self::STATUS_WAIT_PAY,
        ], true);
    }

    /**
     * Отменена ли заявка.
     */
    public function isCancelled(): bool
    {
        return $this->statusInt() === self::STATUS_CANCELLED;
    }


    /**
     * Сумма (если BestMerchant отдаёт; у тебя сейчас inTotal/outTotal есть, но 0).
     * Если нужно — поменяешь на inTotal/outTotal.
     */
    public function getAmount(): ?string
    {
        // Если есть конкретное поле суммы — подставь сюда.
        // Пока возвращаем null/0 не делаем.
        return $this->safeDecimal($this->dataGet('inTotal'));
    }


    /**
     * Валюта платежа.
     */
    public function getCurrency(): ?string
    {
        return $this->safeString($this->dataGet('currency'));
    }

    /**
     * Внешний ID в системе шлюза.
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('id'));
    }

    /**
     * Текст ошибки.
     */
    public function getErrorMessage(): ?string
    {
        // pending и success — не ошибка
        if ($this->isSuccessful() || $this->isPending()) {
            return null;
        }

        // если API отдаёт message/error — используем
        $msg = $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('error'))
            ?? $this->safeString($this->dataGet('error_message'));

        if ($msg !== null) {
            return $msg;
        }

        // fallback по статусу
        return $this->getStatusDescription();
    }

    /**
     * Описание статуса для UI.
     */
    public function getStatusDescription(): string
    {
        return match ($this->statusInt()) {
            self::STATUS_WAIT_CLIENT_CONFIRM => 'Ожидает подтверждения клиента',
            self::STATUS_WAIT_PAY            => 'Ожидает оплаты',
            self::STATUS_CONFIRMED           => 'Заявка подтверждена',
            self::STATUS_CANCELLED           => 'Заявка отменена',
            default                          => 'Статус не определен',
        };
    }
}
