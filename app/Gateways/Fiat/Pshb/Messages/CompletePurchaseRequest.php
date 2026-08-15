<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Pshb\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use InvalidArgumentException;

/**
 * Обработка callback/IPN (completePurchase).
 *
 * Важно:
 * - callback приходит POST (JSON)
 * - содержит финальный result success/error
 * - подпись проверяется в CompletePurchaseResponse
 */
final class CompletePurchaseRequest extends AbstractRequest
{
    /**
     * PSHB присылает callback-уведомление методом POST (JSON).
     *
     * Минимально ожидаем:
     * - project_id
     * - order_id
     * - transaction_id
     * - operation.status
     */
    public function getData(): array
    {
        // Плоские обязательные поля
        $this->validate('project_id', 'order_id', 'transaction_id');

        // 1) Пытаемся получить JSON-body максимально надёжно
        $payload = $this->getParameters();

        if (!is_array($payload) || $payload === []) {
            $raw = (string) request()->getContent();
            $decoded = $raw !== '' ? json_decode($raw, true) : null;
            $payload = is_array($decoded) ? $decoded : [];
        }

        // Минимальная проверка структуры
        if (!is_array($payload) || $payload === []) {
            throw new InvalidArgumentException('PSHB callback: пустой payload.');
        }

        // Проверяем только наличие operation.status
        if (empty($payload['operation']['status'])) {
            throw new InvalidArgumentException('PSHB callback: отсутствует operation.status.');
        }

        $orderId = (string) $payload['order_id'];

        // Внутренний transactionId используем для трекинга/логов
        $this->setTransactionId($orderId);

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
