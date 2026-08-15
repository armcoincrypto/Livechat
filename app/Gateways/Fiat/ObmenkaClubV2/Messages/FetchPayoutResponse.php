<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\ObmenkaClubV2\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPayoutResponse extends AbstractResponse
{
    protected const STATUSES_SUCCESS   = [1];
    protected const STATUSES_PENDING   = [0, 4, 5];
    protected const STATUSES_CANCELLED = [2, 3];

    public function isSuccessful(): bool
    {
        $status = $this->status();
        return $status !== null && in_array($status, self::STATUSES_SUCCESS, true);
    }

    public function isPending(): bool
    {
        $status = $this->status();
        return $status !== null && in_array($status, self::STATUSES_PENDING, true);
    }

    public function isCancelled(): bool
    {
        $status = $this->status();
        return $status !== null && in_array($status, self::STATUSES_CANCELLED, true);
    }

    /**
     * ID выплаты для cron-трекинга.
     *
     * Приоритет:
     *  1) ID от провайдера
     *  2) token из query (то, что мы отправляли в payout)
     *  3) fallback по другим полям
     */
    public function getWithdrawalId(): ?string
    {
        $providerId = $this->safeString($this->dataGet('withdrawal_id'))
            ?? $this->safeString($this->dataGet('result.txn'))
            ?? $this->safeString($this->dataGet('txn'))
            ?? $this->safeString($this->dataGet('tx_id'))
            ?? $this->safeString($this->dataGet('id'));

        if ($providerId !== null) {
            return $providerId;
        }

        // если провайдер не вернул ID — используем то, что отправляли
        return $this->safeString($this->queryGet('token'));
    }

    /**
     * Текущее значение статуса как int.
     */
    private function status(): ?int
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
