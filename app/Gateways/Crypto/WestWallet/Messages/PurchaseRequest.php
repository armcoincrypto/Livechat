<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\WestWallet\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;

/**
 * Создание платежа (incoming).
 */
final class PurchaseRequest extends AbstractRequest
{
    public function getData(): array
    {
        // Пример: минимальная валидация
        $this->validate('amount');

        return [
            'currency' => $this->getMerchantNetworkCode(),
            'label' => (string) $this->getTransactionId(),
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $response = $this->sendRequest('post', '/address/generate', $data);

        return $this->response = new PurchaseResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
