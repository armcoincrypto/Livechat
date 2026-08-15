<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\B2BWallet\Messages;

use iEXPackages\Payments\Core\Contracts\PollingResponseInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class PayoutResponse extends AbstractResponse implements PollingResponseInterface
{
    /**
     * Успешна ли выплата.
     * Тут логика зависит от формата ответа шлюза.
     */
    public function isSuccessful(): bool
    {
        $id = $this->safeString($this->dataGet('id'));

        return $id !== null;
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
        return true;
    }

    public function getPollingType(): ?string
    {
        return 'tx_id';
    }

    public function getPollingKey(): ?string
    {
        return $this->getExternalId();
    }

    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('id'));
    }
}
