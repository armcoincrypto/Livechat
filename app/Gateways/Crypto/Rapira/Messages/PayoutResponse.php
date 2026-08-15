<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Rapira\Messages;

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
     * Успешна ли выплата.
     * Тут логика зависит от формата ответа шлюза.
     */
    public function isSuccessful(): bool
    {
        $id = $this->safeInt($this->dataGet('withdrawRecordId'));

        return $id !== null && $id > 0;
    }

    public function getStatus(): ?string
    {
        if ($this->isSuccessful()) {
            return 'success';
        }

        return null;
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

    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('withdrawRecordId'));
    }
}
