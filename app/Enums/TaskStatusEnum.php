<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskStatusEnum: int
{
    case EXPIRED               = 1;
    case PENDING_PAYMENT       = 2;
    case WAITING_HANDLE        = 3;
    case COMPLETED             = 4;
    case REJECTED              = 5;
    case CANCELED_BY_USER      = 6;
    case PAID                  = 7;
    case FROZEN                = 8;
    case PROCESSING_PAYMENT    = 9;
    case INVALID               = 10;
    case DELETED               = 11;
    case CHECK_PAYMENT         = 12;
    case MERCHANT_CONFIRMATION = 13;
    case AUTO_PAYOUT_ERROR     = 14;
    case PAYOUT_IN_PROGRESS    = 15;
    case PAYOUT_QUEUE          = 16;

    /**
     * Нормализация id (полезно для request/DB/строк).
     */
    public static function normalizeId(int|string|null $id): ?int
    {
        if ($id === null) {
            return null;
        }

        if (is_string($id)) {
            $id = trim($id);
            if ($id === '') {
                return null;
            }
        }

        $intId = (int) $id;

        // если вдруг прилетит 0/отрицательное — считаем невалидным
        return $intId > 0 ? $intId : null;
    }

    /**
     * Безопасно получить enum из id (не упадёт на новых статусах из БД).
     */
    public static function tryFromId(int|string|null $id): ?self
    {
        $intId = self::normalizeId($id);
        return $intId ? self::tryFrom($intId) : null;
    }

    /**
     * Строго получить enum из id (если неизвестный — исключение).
     * Удобно для мест, где статус обязан быть системным.
     */
    public static function fromId(int|string|null $id): self
    {
        $intId = self::normalizeId($id);

        if (!$intId) {
            throw new \InvalidArgumentException('Task status id is empty.');
        }

        return self::from($intId);
    }

    /**
     * Удобное сравнение: enum vs int|string|null
     */
    public function equalsId(int|string|null $id): bool
    {
        $intId = self::normalizeId($id);
        return $intId !== null && $this->value === $intId;
    }

    /**
     * Помогает быстро проверять "входит ли статус в набор".
     */
    public function in(self ...$set): bool
    {
        foreach ($set as $item) {
            if ($item === $this) {
                return true;
            }
        }
        return false;
    }

    /**
     * Категории через битмаску: масштабируется лучше, чем десятки isSomething().
     */
    private const int FLAG_FINAL        = 1 << 0;
    private const int FLAG_PAYMENT_FLOW = 1 << 1;
    private const int FLAG_BAD_STATE    = 1 << 2; // ошибка/некорректность
    private const int FLAG_VISIBLE      = 1 << 3; // условно: показывать в UI/фильтрах

    /**
     * Единый источник правды по "типам" статусов.
     * Расширять — просто добавляешь флаги.
     *
     * Важно:
     * - BAD_STATE ставим только там, где это реально "ошибка/некорректность".
     * - Финальные статусы "закрывают" заявку (переходы запрещены).
     */
    public function flags(): int
    {
        return match ($this) {
            // Финальные (закрывающие)
            self::COMPLETED,
            self::REJECTED,
            self::CANCELED_BY_USER,
            self::EXPIRED
            => self::FLAG_FINAL | self::FLAG_VISIBLE,

            // Финальные и плохие (ошибка/некорректность + закрытие)
            self::DELETED,
            self::INVALID
            => self::FLAG_FINAL | self::FLAG_BAD_STATE | self::FLAG_VISIBLE,

            // Поток оплаты / авто-выплаты (не финал)
            self::PENDING_PAYMENT,
            self::PROCESSING_PAYMENT,
            self::CHECK_PAYMENT,
            self::MERCHANT_CONFIRMATION,
            self::PAID,
            self::PAYOUT_QUEUE,
            self::PAYOUT_IN_PROGRESS
            => self::FLAG_PAYMENT_FLOW | self::FLAG_VISIBLE,

            // Ошибка авто-выплаты (не финал, но "плохое" состояние)
            self::AUTO_PAYOUT_ERROR
            => self::FLAG_PAYMENT_FLOW | self::FLAG_BAD_STATE | self::FLAG_VISIBLE,

            // Остальные (например FROZEN)
            default => self::FLAG_VISIBLE,
        };
    }

    private function hasFlag(int $flag): bool
    {
        return (bool) ($this->flags() & $flag);
    }

    public function isFinal(): bool
    {
        return $this->hasFlag(self::FLAG_FINAL);
    }

    public function isPaymentFlow(): bool
    {
        return $this->hasFlag(self::FLAG_PAYMENT_FLOW);
    }

    public function isBadState(): bool
    {
        return $this->hasFlag(self::FLAG_BAD_STATE);
    }

    /**
     * Именованные наборы — оставляем для удобства, но базируем на enum'ах.
     */
    public static function finalSet(): array
    {
        return [
            self::COMPLETED,
            self::REJECTED,
            self::CANCELED_BY_USER,
            self::EXPIRED,
            self::DELETED,
            self::INVALID,
        ];
    }

    public static function paymentSet(): array
    {
        return [
            self::PENDING_PAYMENT,
            self::PROCESSING_PAYMENT,
            self::CHECK_PAYMENT,
            self::MERCHANT_CONFIRMATION,
            self::PAID,
            self::PAYOUT_QUEUE,
            self::PAYOUT_IN_PROGRESS,
            self::AUTO_PAYOUT_ERROR,
        ];
    }

    /**
     * ✅ State Machine (переходы).
     * Используй это как единый источник разрешённых переходов.
     */
    public function canTransitionTo(self $to): bool
    {
        // финальные статусы “заморожены”
        if ($this->isFinal()) {
            return false;
        }

        return match ($this) {
            self::PENDING_PAYMENT => $to->in(
                self::PROCESSING_PAYMENT,
                self::CHECK_PAYMENT,
                self::WAITING_HANDLE,
                self::MERCHANT_CONFIRMATION,
                self::REJECTED,
                self::CANCELED_BY_USER,
                self::EXPIRED
            ),

            self::PROCESSING_PAYMENT => $to->in(
                self::CHECK_PAYMENT,
                self::PAID,
                self::CANCELED_BY_USER,
                self::EXPIRED
            ),

            self::CHECK_PAYMENT => $to->in(
                self::PAID,
                self::REJECTED,
                self::PENDING_PAYMENT
            ),

            // Оплата подтверждена -> либо дальше в обработку, либо ждём мерчанта, либо ставим в очередь на выплату
            self::PAID => $to->in(
                self::WAITING_HANDLE,
                self::MERCHANT_CONFIRMATION,
                self::PAYOUT_QUEUE,
                self::COMPLETED
            ),

            self::MERCHANT_CONFIRMATION => $to->in(
                self::PAID,
                self::WAITING_HANDLE,
                self::CHECK_PAYMENT
            ),

            self::WAITING_HANDLE => $to->in(
                self::PAID,
                self::COMPLETED,
                self::REJECTED,
                self::FROZEN
            ),

            self::FROZEN => $to->in(
                self::WAITING_HANDLE,
                self::REJECTED
            ),

            // Очередь -> запуск выплаты
            self::PAYOUT_QUEUE => $to->in(
                self::PAYOUT_IN_PROGRESS
            ),

            // Выплата -> финал или ошибка
            self::PAYOUT_IN_PROGRESS => $to->in(
                self::COMPLETED,
                self::AUTO_PAYOUT_ERROR
            ),

            // Ошибка выплаты -> можно повторить выплату или отклонить
            self::AUTO_PAYOUT_ERROR => $to->in(
                self::PAYOUT_IN_PROGRESS,
                self::REJECTED
            ),

            default => false,
        };
    }
}
