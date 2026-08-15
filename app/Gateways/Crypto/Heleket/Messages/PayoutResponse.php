<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Heleket\Messages;

use iEXPackages\Payments\Core\Contracts\PayoutTrackingResponseInterface;
use iEXPackages\Payments\Core\Contracts\PollingResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class PayoutResponse extends AbstractResponse implements PollingResponseInterface, PayoutTrackingResponseInterface
{
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
     * Выплата "создана успешно", если:
     * - state === 0
     * - result.uuid не пустой
     */
    public function isSuccessful(): bool
    {
        return (int) ($this->dataGet('state') ?? 1) === 0
            && $this->safeString($this->dataGet('result.uuid')) !== null
            && $this->safeString($this->dataGet('result.uuid')) !== '';
    }

    /**
     * Статус выплаты в терминах Heleket (result.status).
     */
    public function getStatus(): ?string
    {
        $status = $this->safeString($this->dataGet('result.status'));
        $status = $status !== null ? strtolower($status) : null;

        return $status !== '' ? $status : null;
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
     * Внешний ID выплаты у провайдера (uuid).
     * Это то, что раньше было withdrawal_id.
     */
    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('result.uuid'));
    }
}
