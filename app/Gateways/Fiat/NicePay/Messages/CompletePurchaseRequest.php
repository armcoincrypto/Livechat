<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\NicePay\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use InvalidArgumentException;

/**
 * Обработка callback/IPN (completePurchase).
 *
 * Важно:
 * - callback приходит GET
 * - содержит финальный result success/error
 * - подпись проверяется в CompletePurchaseResponse
 */
final class CompletePurchaseRequest extends AbstractRequest
{
    /**
     * NicePay присылает callback по финальному статусу (GET).
     * Мы обязаны проверить наличие ключевых полей.
     */
    public function getData(): array
    {
        // NicePay присылает callback по финальному статусу (GET).
        // Мы обязаны проверить наличие ключевых полей.
        $this->validate(
            'payment_id',
            'merchant_id',
            'order_id',
            'amount',
            'hash'
        );

        $payload = $this->getParameters();

        // order_id — это ID заявки/платежа в нашей системе (может быть строкой).
        $orderId = $payload['order_id'] ?? null;
        $orderId = is_scalar($orderId) ? trim((string) $orderId) : '';

        if ($orderId === '') {
            throw new InvalidArgumentException('NicePay callback: отсутствует корректный order_id.');
        }

        // Внутренний transactionId используем для трекинга/логов
        $this->setTransactionId($orderId);

        if (!is_array($payload) || $payload === []) {
            throw new InvalidArgumentException('NicePay callback: payload пустой.');
        }

        return $payload;
    }


    /**
     * Создаем ответ из входящих данных callback, без API вызовов.
     */
    protected function sendData(array $data): ResponseInterface
    {
        return $this->response = new CompletePurchaseResponse(
            request: $this,
            data: is_array($data) ? $data : [],
            query: $this->getParameters(),
        );
    }
}
