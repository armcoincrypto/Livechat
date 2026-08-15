<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\PayCore\Messages;

use iEXPackages\Payments\Core\Contracts\FetchPaymentResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPaymentResponse extends AbstractResponse implements FetchPaymentResponseInterface
{
    // BestMerchant: числовые статусы
    private const STATUS_IN_PROGRESS          = 0;
    private const STATUS_WAIT_BANK            = 1;
    private const STATUS_OK                   = 2;  // финальный успех
    private const STATUS_HOLD                 = 3;
    private const STATUS_CANCELED             = 4;  // финальный
    private const STATUS_ERROR_ANTIFRAUD      = 5;  // промежуточный
    private const STATUS_ERROR_BANK           = 6;  // финальный
    private const STATUS_REFUND_REQUESTED     = 7;  // промежуточный
    private const STATUS_REFUNDED             = 8;  // финальный
    private const STATUS_INIT_ERROR           = 9;  // финальный
    private const STATUS_TIMEOUT_ERROR        = 10; // финальный

    /** Успех */
    private const SUCCESS_STATUSES = [
        self::STATUS_OK,
    ];

    /** Ожидание / промежуточные состояния */
    private const PENDING_STATUSES = [
        self::STATUS_IN_PROGRESS,
        self::STATUS_WAIT_BANK,
        self::STATUS_HOLD,
        self::STATUS_ERROR_ANTIFRAUD,
        self::STATUS_REFUND_REQUESTED,
    ];

    /** Финальные НЕуспехи (для isCancelled) */
    private const CANCELLED_FINAL_STATUSES = [
        self::STATUS_CANCELED,
        self::STATUS_ERROR_BANK,
        self::STATUS_REFUNDED,
        self::STATUS_INIT_ERROR,
        self::STATUS_TIMEOUT_ERROR,
    ];

    /**
     * Найден ли объект платежа в системе (существует ли запись).
     */
    public function exists(): bool
    {
        $merchantId = $this->merchantIdInt();
        if ($merchantId === null || $merchantId <= 0) {
            return false;
        }

        $taskId = $this->getTask()?->id;
        if ($taskId !== null) {
            return (int) $merchantId === (int) $taskId;
        }

        return true;
    }

    /**
     * Возвращает статус как строку (для единообразного API).
     */
    public function getStatus(): ?string
    {
        $s = $this->statusInt();
        return $s === null ? null : (string) $s;
    }

    public function isSuccessful(): bool
    {
        $s = $this->statusInt();
        return $this->exists() && $s !== null && in_array($s, self::SUCCESS_STATUSES, true);
    }

    public function isPending(): bool
    {
        $s = $this->statusInt();
        return $this->exists() && $s !== null && in_array($s, self::PENDING_STATUSES, true);
    }

    public function isCancelled(): bool
    {
        $s = $this->statusInt();
        return $this->exists() && $s !== null && in_array($s, self::CANCELLED_FINAL_STATUSES, true);
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

    /**
     * Сумма (если BestMerchant отдаёт; у тебя сейчас inTotal/outTotal есть, но 0).
     * Если нужно — поменяешь на inTotal/outTotal.
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
        return $this->safeString($this->dataGet('currency_from'))
            ?? $this->safeString($this->dataGet('currency_to'));
    }

    /**
     * Внешний ID провайдера (external_id).
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('merchant_id'));
    }


    /**
     * Текст ошибки.
     */
    public function getErrorMessage(): ?string
    {
        if (!$this->exists()) {
            return 'Транзакция не найдена';
        }

        if ($this->isSuccessful() || $this->isPending()) {
            return null;
        }

        // На случай если провайдер возвращает error/message
        $msg = $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('error'))
            ?? $this->safeString($this->dataGet('error_message'));

        return ($msg !== null && $msg !== '') ? $msg : $this->getStatusDescription();
    }

    /**
     * Описание статуса для UI.
     */
    public function getStatusDescription(): string
    {
        $s = $this->statusInt();

        return match ($s) {
            self::STATUS_IN_PROGRESS      => 'Операция в обработке',
            self::STATUS_WAIT_BANK        => 'Ожидаем подтверждение банка',
            self::STATUS_OK               => 'Операция успешно завершена',
            self::STATUS_HOLD             => 'Операция на холде',
            self::STATUS_CANCELED         => 'Операция отменена',
            self::STATUS_ERROR_ANTIFRAUD  => 'Проверка антифрода: требуется ожидание/решение',
            self::STATUS_ERROR_BANK       => 'Ошибка банка',
            self::STATUS_REFUND_REQUESTED => 'Запрошен возврат средств',
            self::STATUS_REFUNDED         => 'Средства возвращены',
            self::STATUS_INIT_ERROR       => 'Ошибка инициализации операции',
            self::STATUS_TIMEOUT_ERROR    => 'Время операции истекло',
            null                          => 'Статус не определён',
            default                       => 'Неизвестный статус: ' . (string) $s,
        };
    }

    private function merchantIdInt(): ?int
    {
        $raw = $this->dataGet('merchant_id');

        if (is_int($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '' && is_numeric($raw)) {
            return (int) $raw;
        }

        return null;
    }
}
