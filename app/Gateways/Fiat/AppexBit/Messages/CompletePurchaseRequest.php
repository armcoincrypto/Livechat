<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\AppexBit\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use InvalidArgumentException;

/**
 * Обработка callback/IPN (completePurchase).
 *
 * Важно:
 * - Этот Request НЕ отправляет HTTP.
 * - Он принимает входящие данные (payload) от контроллера/роута.
 */
final class CompletePurchaseRequest extends AbstractRequest
{
    /**
     * Получить данные callback (payload) и привести к нужному виду.
     */
    public function getData(): array
    {
        // В новой архитектуре callback payload приходит через $parameters при создании request.
        // Т.е. gateway->request('complete_purchase', $request->all())
        $payload = $this->getParameters(); // или $this->getQuery(), если у тебя так называется

        if (!is_array($payload) || $payload === []) {
            throw new InvalidArgumentException('Callback payload пустой.');
        }

        // Валидация обязательных
        $this->validate('externalId', 'amountFiat');

        return $payload;
    }

    /**
     * Создать response на основе входящих данных.
     * HTTP вызова здесь нет.
     */
    protected function sendData(array $data): ResponseInterface
    {
        return $this->response = new CompletePurchaseResponse(
            $this,
            is_array($data) ? $data : [],
            $this->getParameters()
        );
    }
}
