<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\PayCore\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class CompletePurchaseResponse extends AbstractResponse
{
    // --- Статусы PayCore ---
    private const STATUS_IN_PROGRESS      = 0;
    private const STATUS_WAIT_BANK        = 1;
    private const STATUS_OK               = 2;
    private const STATUS_HOLD             = 3;
    private const STATUS_CANCELED         = 4;
    private const STATUS_ERROR_ANTIFRAUD  = 5;
    private const STATUS_ERROR_BANK       = 6;
    private const STATUS_REFUND_REQUESTED = 7;
    private const STATUS_REFUNDED         = 8;
    private const STATUS_INIT_ERROR       = 9;
    private const STATUS_TIMEOUT_ERROR    = 10;

    /** Финальный успех */
    private const SUCCESS_STATUSES = [
        self::STATUS_OK,
    ];

    /** Промежуточные */
    private const PENDING_STATUSES = [
        self::STATUS_IN_PROGRESS,
        self::STATUS_WAIT_BANK,
        self::STATUS_HOLD,
        self::STATUS_ERROR_ANTIFRAUD,
        self::STATUS_REFUND_REQUESTED,
    ];

    /** Финальные неуспехи */
    private const CANCELLED_FINAL_STATUSES = [
        self::STATUS_CANCELED,
        self::STATUS_ERROR_BANK,
        self::STATUS_REFUNDED,
        self::STATUS_INIT_ERROR,
        self::STATUS_TIMEOUT_ERROR,
    ];

    // ---------------------------------------------------------------------

    public function isSuccessful(): bool
    {
        return in_array($this->getStatusCode(), self::SUCCESS_STATUSES, true);
    }

    public function isPending(): bool
    {
        return in_array($this->getStatusCode(), self::PENDING_STATUSES, true);
    }

    public function isCancelled(): bool
    {
        return in_array($this->getStatusCode(), self::CANCELLED_FINAL_STATUSES, true);
    }

    /**
     * Внешний ID транзакции у PayCore
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('external_id'));
    }

    /**
     * ID заявки в нашей системе (обычно кладёшь сюда external_id при создании)
     */
    public function getOrderId(): ?string
    {
        return $this->safeString($this->dataGet('merchant_id'));
    }

    public function getAmount(): ?string
    {
        return $this->safeDecimal($this->dataGet('amount'));
    }

    public function getCurrency(): ?string
    {
        return $this->safeString($this->dataGet('currency_from'))
            ?? $this->safeString($this->dataGet('currency_to'));
    }

    /**
     * Текст ошибки для логов / UI
     */
    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful() || $this->isPending()) {
            return null;
        }

        return 'PayCore: финальный неуспешный статус (' . $this->getStatusCode() . ')';
    }

    // ---------------------------------------------------------------------

    private function getStatusCode(): ?int
    {
        $raw = $this->statusInt();

        if (is_int($raw)) {
            return $raw;
        }

        if (is_string($raw) && is_numeric($raw)) {
            return (int) $raw;
        }

        return null;
    }

    private function statusInt(): ?int
    {
        $raw = $this->dataGet('status');

        if (is_int($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '' && is_numeric($raw)) {
            return (int) $raw;
        }

        // В примере у тебя status=20 — это тоже число, но вне спецификации.
        // Мы всё равно вернём int, чтобы корректно показать "Неизвестный статус: 20"
        if (is_numeric($raw)) {
            return (int) $raw;
        }

        return null;
    }
}
