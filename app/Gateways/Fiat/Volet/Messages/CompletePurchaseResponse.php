<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Volet\Messages;

use iEXPackages\Payment\Exception\InvalidResponseException;
use iEXPackages\Payments\Core\Contracts\RequestInterface;
use iEXPackages\Payments\Core\Engine\AbstractResponse;

final class CompletePurchaseResponse extends AbstractResponse
{
    public function __construct(
        RequestInterface $request,
        array $data
    ) {
        parent::__construct($request, $data);

        if(app()->isProduction()) {
            if ($this->getSign() !== $this->calculateSignature()) {
                throw new InvalidResponseException(
                    'Хэш callback не соответствует ожидаемому значению'
                );
            }
        }
    }

    public function isSuccessful(): bool
    {
        return $this->safeString($this->dataGet('ac_transaction_status')) === 'COMPLETED';
    }

    public function isCancelled(): bool
    {
        return (int) $this->dataGet('ac_transfer') === 0;
    }

    public function getExternalId(): ?string
    {
        return $this->safeString($this->dataGet('ac_transfer'));
    }


    /**
     * Внутренний ID заявки/ордера в нашей системе (если AppexBit его возвращает).
     */
    public function getOrderId(): ?string
    {
        return $this->safeString($this->dataGet('ac_order_id'));
    }

    public function getAmount(): ?string
    {
        return $this->safeDecimal(
            $this->dataGet('ac_buyer_amount_without_commission')
        );
    }

    public function getCurrency(): ?string
    {
        return str_replace(
            'RUR',
            'RUB',
            (string) $this->dataGet('ac_merchant_currency')
        );
    }

    /**
     * Статус как строка для унификации (опционально).
     */
    public function getSign(): ?string
    {
        return $this->safeString($this->dataGet('ac_hash'));
    }


    /**
     * Локальный расчёт подписи (как в документации Volet)
     */
    private function calculateSignature(): string
    {
        return hash('sha256', implode(':', [
            $this->dataGet('ac_transfer'),
            $this->dataGet('ac_start_date'),
            $this->dataGet('ac_sci_name'),
            $this->dataGet('ac_src_wallet'),
            $this->dataGet('ac_dest_wallet'),
            $this->dataGet('ac_order_id'),
            $this->dataGet('ac_amount'),
            $this->dataGet('ac_merchant_currency'),
            $this->request->getSciPassword(),
        ]));
    }
}
