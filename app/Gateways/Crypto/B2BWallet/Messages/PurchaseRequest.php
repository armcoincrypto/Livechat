<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\B2BWallet\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;

/**
 * Создание платежа (incoming).
 */
final class PurchaseRequest extends AbstractRequest
{
    public function getData(): array
    {
        $this->validate('amount');
        $this->validateConfig('api_key');


        return [
            'coin' => $this->getMerchantNetworkCode(),
            'label' => (string) $this->getTransactionId(),
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {

        $response = $this->sendRequest('post', '/api/v1/address', $data);

        return $this->response = new PurchaseResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
