<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Rapira\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class PurchaseResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        if (!$this->dataHas('id')) {
            return false;
        }

        $id = $this->dataGet('id');

        return is_numeric($id) && (int)$id > 0;
    }

    public function getAccountNumber(): ?string
    {
        return $this->safeString($this->dataGet('address'));
    }

    public function getCurrency(): ?string
    {
        return $this->safeString($this->dataGet('blockchain'));
    }

    public function getAccountTag(): ?string
    {
        return $this->safeString($this->dataGet('memo'));
    }

    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('address'));
    }
}
