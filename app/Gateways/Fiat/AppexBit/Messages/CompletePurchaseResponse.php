<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\AppexBit\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class CompletePurchaseResponse extends AbstractResponse
{
    private const STATUS_SUCCESS = 3;

    public function isSuccessful(): bool
    {
        return $this->statusInt() === self::STATUS_SUCCESS;
    }

    /**
     * Если транзакция отменена/неуспешна.
     *
     * Если у AppexBit есть отдельные pending-статусы — лучше вынести их в isPending().
     * Пока оставляем как у тебя: всё, что не success — считается отменой/неуспехом.
     */
    public function isCancelled(): bool
    {
        $status = $this->statusInt();
        return $status !== null && $status !== self::STATUS_SUCCESS;
    }

    /**
     * Внутренний ID заявки/ордера в нашей системе (если AppexBit его возвращает).
     */
    public function getOrderId(): ?string
    {
        return $this->safeString($this->dataGet('offerId'));
    }

    /**
     * Внешний идентификатор операции (externalId).
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('externalId'));
    }

    public function getAmount(): ?string
    {
        return $this->safeDecimal($this->dataGet('amountFiat'));
    }

    /**
     * Валюта (фиксировано).
     */
    public function getCurrency(): ?string
    {
        return 'RUB';
    }

    /**
     * Статус как строка для унификации (опционально).
     */
    public function getStatus(): ?string
    {
        $status = $this->statusInt();
        return $status === null ? null : (string) $status;
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

        return null;
    }
}
