<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\BestMerchant\Messages;

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
            'InTotal' => (float) $this->getAmount(),
            'PayMethod' => 'CARD'
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $response = $this->sendRequest('post', '/api/smart-orders', $data, 'asJson');

        return $this->response = new PurchaseResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
