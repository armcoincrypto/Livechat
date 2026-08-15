<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\CryptoCash\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;

/**
 * Создание платежа (incoming).
 */
final class PurchaseRequest extends AbstractRequest
{
    public function getData(): array
    {
        $this->validate('amount');

        return [
            'amount' => (string)$this->getAmount(),
            'currency' => $this->getCurrency(),
            'network'     => $this->getMerchantNetworkCode(),
            'externalId' => (string)$this->getTransactionId(),
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $response = $this->sendRequest('post', '/merchant/api/v1/balance/actions/sale', $data);

        return $this->response = new PurchaseResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
