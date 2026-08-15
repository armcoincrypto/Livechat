<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Kobbopay\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class OptionsResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return is_array($this->getData());
    }

    public function getOptions(): array
    {
        return $this->getData();
    }

    public function getErrorMessage(): ?string
    {
        return null;
    }
}
