<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\PayCore\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;

/**
 * Создание платежа (incoming).
 */
final class PurchaseRequest extends AbstractRequest
{
    public function getData(): array
    {
        // Пример: минимальная валидация
        $this->validate('amount', 'currency');

        $task = $this->getTask();

        $fromShot = $this->cleanString((string) ($task->from_shot ?? ''));
        $notifyUrl = (string) $this->getParameter('notifyUrl', ''). '?id='. $this->getTransactionId();

        return [
            'amount' => (string) $this->getAmount(),
            'phone_number' => (string) $fromShot,
            'transaction_type' => 'payment',
            'callback_url' => $notifyUrl
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $hash = hash('sha256', $this->getMerchantToken() . $this->getTransactionId());

        $response = $this->sendRequest('post', '/api/v2/exchanger/'. $hash, $data);

        return $this->response = new PurchaseResponse(
            $this,
            is_array($response) ? [
                'hash' => $hash,
                'url' => $response['url'] ?? ''
            ] : [],
            $data
        );
    }
}
