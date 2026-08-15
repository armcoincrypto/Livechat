<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Volet\Messages;

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
        $this->validate(
            'ac_src_wallet', 'ac_transfer', 'ac_order_id'
        );

        // В новой архитектуре callback payload приходит через $parameters при создании request.
        // Т.е. gateway->request('complete_purchase', $request->all())
        $payload = $this->getParameters(); // или $this->getQuery(), если у тебя так называется

        if (!is_array($payload) || $payload === []) {
            throw new InvalidArgumentException('Callback payload пустой.');
        }

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
