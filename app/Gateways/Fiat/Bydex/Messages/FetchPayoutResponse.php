<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Bydex\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class FetchPayoutResponse extends AbstractResponse
{
    private const STATUSES_SUCCESS   = [1];
    private const STATUSES_PENDING   = [0, 4, 5];
    private const STATUSES_CANCELLED = [2, 3];

    public function isSuccessful(): bool
    {
        return in_array($this->status(), self::STATUSES_SUCCESS, true);
    }

    public function isPending(): bool
    {
        return in_array($this->status(), self::STATUSES_PENDING, true);
    }

    public function isCancelled(): bool
    {
        return in_array($this->status(), self::STATUSES_CANCELLED, true);
    }

    /**
     * Внешний ID выплаты (для cron-трекинга).
     */
    public function getWithdrawalId(): ?string
    {
        return $this->safeString($this->dataGet('withdrawal_id'))
            ?? $this->safeString($this->dataGet('txn'))
            ?? $this->safeString($this->dataGet('tx_id'))
            ?? $this->safeString($this->dataGet('id'));
    }

    /**
     * Локальный helper — без отдельного statusInt().
     */
    private function status(): ?int
    {
        $raw = $this->dataGet('status');

        if (is_int($raw)) {
            return $raw;
        }

        if (is_string($raw) && is_numeric($raw)) {
            return (int) $raw;
        }

        return null;
    }
}
