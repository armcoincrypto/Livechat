<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\SuperMoney\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;

/**
 * Создание платежа (incoming).
 */
final class PurchaseRequest extends AbstractRequest
{
    public function getData(): array
    {
        // Пример: минимальная валидация
        // $this->validate('amount', 'currency');

        return [
            'amount' => (float)$this->getAmount(),
            'currency'  => $this->getCurrency(),
            'extId'  => (string)$this->getTransactionId()
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $typePay = strtolower($this->payString('type_pay', 'card'));
        $path = $this->resolvePayoutPathByType($typePay);
        $response = $this->sendRequest('post', $path, $data);

        return $this->response = new PurchaseResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }

    protected function resolvePayoutPathByType(string $typePay): string
    {
        return match ($typePay) {
            'sbp'  => '/v2/merchant/transactions/sbp',
            default => '/v2/merchant/transactions',
        };
    }
}
