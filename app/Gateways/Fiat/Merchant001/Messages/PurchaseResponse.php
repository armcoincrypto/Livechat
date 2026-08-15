<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Merchant001\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

/**
 * Ответ на создание платежа (incoming).
 */
final class PurchaseResponse extends AbstractResponse
{
    /**
     * Базовая логика успешности (шаблон).
     * TODO: замени под реальный ответ API.
     */
    public function isSuccessful(): bool
    {
        // пример: если есть id
        return $this->dataHas('id') && is_numeric($this->dataGet('id')) && (int)$this->dataGet('id') > 0;
    }
}
