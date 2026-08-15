<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Contracts;

/**
 * Контракт для ответа payout(), когда результат выплаты будет подтверждён позже
 * (через cron/fetchPayout или callback).
 */
interface PayoutTrackingResponseInterface
{
    /**
     * Нужно ли включать отслеживание после payout().
     * true → переводим в статус "ожидаем выплату" и запускаем follow-up.
     */
    public function isTrackingRequired(): bool;

    /**
     * Способ отслеживания: cron|callback.
     */
    public function getTrackingMode(): string; // 'cron'|'callback'

    /**
     * Ключ для отслеживания (withdrawal_id / record_id / tx_hash).
     *
     * Можно вернуть null только если trackingMode=callback и провайдер шлёт
     * callback с однозначной связкой без ключа.
     */
    public function getTrackingKey(): ?string;

    /**
     * Нужно ли откладывать успех до финального подтверждения.
     * true → payout() создал выплату, но "успех" не ставим.
     */
    public function isSuccessDeferred(): bool;
}
