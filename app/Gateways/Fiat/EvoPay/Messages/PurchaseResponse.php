<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\EvoPay\Messages;

use iEXPackages\Payments\Core\Engine\AbstractResponse;

/**
 * Ответ на создание платежа (incoming) для BestMerchant.
 */
final class PurchaseResponse extends AbstractResponse
{
    /**
     * Платёж считается успешно созданным,
     * если API вернул внешний идентификатор (id).
     */
    public function isSuccessful(): bool
    {
        return $this->safeString($this->dataGet('order.id')) !== null;
    }

    /**
     * Реквизиты для приёма средств (адрес / счёт).
     */
    public function getAccountNumber(): ?string
    {
        $method = $this->safeString($this->dataGet('order.paymentMethod')) ?? '';
        $requisites = $this->dataGet('order.requisites') ?? [];

        if (!is_array($requisites)) {
            return '';
        }

        return $method === 'SBP'
            ? (string) ($requisites['recipient_phone_number'] ?? '')
            : (string) ($requisites['recipient_card_number'] ?? '');
    }

    /**
     * Название банка / платёжного метода (если применимо).
     */
    public function getBankName(): ?string
    {
        return (string) ($this->dataGet('order.requisites.recipient_bank') ?? '');
    }

    /**
     * Дополнительное описание реквизитов (назначение, комментарий и т.п.).
     */
    public function getAccountTag(): ?string
    {
        return (string) ($this->dataGet('order.requisites.recipient_full_name') ?? '');
    }

    /**
     * Валюта платежа.
     */
    public function getCurrency(): ?string
    {
        return (string) ($this->dataGet('order.fiatCurrencyCode') ?? '');
    }

    /**
     * Внешний идентификатор платежа в системе шлюза.
     */
    public function getExternalId(): ?string
    {
        return (string) ($this->dataGet('order.id') ?? '');
    }

    public function getErrorMessage(): ?string
    {
        if ($this->isSuccessful()) {
            return null;
        }

        return $this->safeString($this->dataGet('message'))
            ?? 'Не удалось получить реквизиты для оплаты';
    }
}
