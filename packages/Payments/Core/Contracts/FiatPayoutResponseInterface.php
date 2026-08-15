<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Contracts;

/**
 * Контракт для ответов выплат (fiat), которые требуют дальнейшей проверки статуса.
 *
 * Используется, когда:
 * - payout создан, но финальный статус появится позже,
 * - нужно запускать cron/polling по withdrawal_id,
 * - или ждать callback от провайдера.
 */
interface FiatPayoutResponseInterface
{
    /**
     * Требуется ли дальнейшая проверка выплаты.
     *
     * Если false — выплату можно считать финальной (success/failed/cancelled).
     */
    public function isFiatPayoutTrackingRequired(): bool;

    /**
     * Уникальный идентификатор выплаты в системе провайдера
     * (withdrawal_id / payout_id / transfer_id).
     *
     * Это именно то, что ты хочешь хранить в базе и по чему потом проверять статус.
     */
    public function getWithdrawalId(): ?string;

    /**
     * Режим проверки статуса выплаты.
     *
     * - cron     → polling по API (worker/cron)
     * - callback → ждём webhook/callback
     * - both     → возможно оба
     * - none     → не требуется (обычно когда trackingRequired=false)
     */
    public function getTrackingMode(): string;

    /**
     * Нужно ли запускать cron/polling проверки (удобный сахар).
     * Обычно true, если mode=cron|both.
     */
    public function shouldCheckByCron(): bool;

    /**
     * Нужно ли ожидать callback/webhook (удобный сахар).
     * Обычно true, если mode=callback|both.
     */
    public function shouldWaitCallback(): bool;

    /**
     * Отложенный успех:
     * payout принят, но финальный результат будет позже.
     */
    public function isDeferredSuccess(): bool;
}
