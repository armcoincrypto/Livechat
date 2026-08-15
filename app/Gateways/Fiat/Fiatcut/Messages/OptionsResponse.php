<?php

namespace App\Gateways\Fiat\Fiatcut\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class OptionsResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        // Успех: если получили массив (может быть пустым — это тоже нормально)
        return is_array($this->getData());
    }

    /**
     * Готовые options value=>label
     */
    public function getOptions(): array
    {
        return $this->getData();
    }

    public function getErrorMessage(): ?string
    {
        // если ты в OptionsRequest ловишь ошибки и формируешь message — тогда бери из data/query
        return null;
    }
}
