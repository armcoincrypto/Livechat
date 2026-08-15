<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Exnode\Messages;

use iEXPackages\Payments\Core\Contracts\PayoutTrackingResponseInterface;
use iEXPackages\Payments\Core\Contracts\PollingResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class PayoutResponse extends AbstractResponse implements PollingResponseInterface, PayoutTrackingResponseInterface
{
    private const STATUS_ACCEPTED = 'ACCEPTED';

    /**
     * Payout создан успешно, если:
     * - есть tracker_id
     * - status === ACCEPTED
     */
    public function isSuccessful(): bool
    {
        $trackerId = $this->safeString($this->dataGet('tracker_id'));
        $status    = $this->status();

        return $trackerId !== null && $trackerId !== ''
            && $status === self::STATUS_ACCEPTED;
    }

    public function isTrackingRequired(): bool
    {
        return true;
    }

    public function getTrackingMode(): string
    {
        return 'cron';
    }

    public function getTrackingKey(): ?string
    {
        return 'tracker_key';
    }

    public function isSuccessDeferred(): bool
    {
        return true;
    }

    /**
     * Статус (верхний регистр).
     */
    public function getStatus(): ?string
    {
        return $this->status();
    }

    private function status(): ?string
    {
        $status = $this->safeString($this->dataGet('status'));
        return $status !== null ? strtoupper($status) : null;
    }

    public function isPollingRequired(): bool
    {
        // payout создан, но tx_hash ещё появится позже → нужен cron
        return true;
    }

    public function getPollingType(): ?string
    {
        return 'tx_hash';
    }

    public function getPollingKey(): ?string
    {
        return $this->getExternalId();
    }

    /**
     * Внешний ID выплаты у провайдера.
     * В старой версии это withdrawal_id = tracker_id.
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('tracker_id'));
    }

    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful()) {
            return null;
        }

        // старый смысл: "Выплата не прошла"
        $msg = $this->safeString($this->dataGet('message'))
            ?? $this->safeString($this->dataGet('error'))
            ?? 'Выплата не прошла';

        // если статус есть — добавим
        $status = $this->status();
        if ($status !== null && $status !== '') {
            return $msg . ' (status=' . $status . ')';
        }

        return $msg;
    }
}
