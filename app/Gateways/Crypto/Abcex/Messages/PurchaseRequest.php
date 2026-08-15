<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Abcex\Messages;

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

        $merchantData = $this->getMerchant();
        $walletId = ($merchantData->ext_options['wallet_unique_id'] ?? 0);

        return [
            'networkId' => $this->getMerchantNetworkCode(),
            'walletId' => (string)$walletId,
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $response = $this->sendRequest('get', '/api/v1/wallet/get-new-crypto-address', $data);

        return $this->response = new PurchaseResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
