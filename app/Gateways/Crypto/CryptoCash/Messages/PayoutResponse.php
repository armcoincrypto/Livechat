<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\CryptoCash\Messages;

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
        $withdrawalId = $this->safeString($this->dataGet('data.item.externalId'));
        return $withdrawalId !== null && $withdrawalId !== '';
    }

    public function getStatus(): ?string
    {
        // "success" тут означает: операция создания payout принята (не финал в блокчейне)
        return $this->isSuccessful() ? 'success' : null;
    }

    public function isPollingRequired(): bool
    {
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
        return $this->safeString($this->dataGet('data.item.externalId'));
    }
}
