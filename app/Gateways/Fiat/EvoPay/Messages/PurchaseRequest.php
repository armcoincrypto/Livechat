<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\EvoPay\Messages;

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

        $typePay = strtolower($this->merchantString('type_pay', 'card'));
        $paymentMethod = ($typePay === 'card') ? 'BANK_CARD' : 'SBP';


        return [
            'customId' => 'ORDER_ID_' . $this->getTransactionId(),
            'paymentMethod' => $paymentMethod,
            'fiatSum' => (float) $this->getAmount(),
            'fiatCurrencyCode' => $this->getCurrency(),
            'cryptoCurrencyCode' => 'USDT'
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $path = '/v1/api/order/payin';

        // 1) Первый запрос
        $httpResponse = $this->sendRequest('get', '/v1/api/order/list', [
            'order_type' => 'PAYIN',
            'limit' => 100
        ], 'asJson');


        // 2) Определяем статус
        $orderStatus = $this->extractOrderStatus($httpResponse);

        // 3) Повторы, пока CREATED
        $maxAttempts = 5;
        $attempt = 1;

        while ($orderStatus === 'CREATED' && $attempt <= $maxAttempts) {
            sleep(3);

            $statusResponse = $this->sendRequest('post', $path, $data, 'asJson');

            $candidate = $this->extractOrderStatus($statusResponse);

            // Если CREATED на одном из уровней — продолжаем
            if ($candidate === 'CREATED') {
                $attempt++;
                $httpResponse = $statusResponse; // держим последнее (для дебага)
                continue;
            }

            // Статус изменился — фиксируем и выходим
            $orderStatus = $candidate;
            $httpResponse = $statusResponse;
            break;
        }

        // 4) Финальные проверки (как у тебя было)
        if ($orderStatus === 'EXPIRE') {
            throw new \RuntimeException('Ошибка: реквизиты не найдены (статус EXPIRE)');
        }

        if ($orderStatus === 'CREATED') {
            throw new \RuntimeException('Ошибка: лимит повторных попыток, реквизиты не получены.');
        }

        // 5) Response
        return $this->response = new PurchaseResponse(
            $this,
            is_array($httpResponse) ? $httpResponse : [],
            $data
        );
    }

    /**
     * Достаёт orderStatus из ответа, поддерживая оба формата:
     * - ['orderStatus' => '...']
     * - ['order' => ['orderStatus' => '...']]
     */
    private function extractOrderStatus(array $payload): ?string
    {
        $s1 = $payload['orderStatus'] ?? null;
        if (is_string($s1) && $s1 !== '') {
            return $s1;
        }

        $s2 = $payload['order']['orderStatus'] ?? null;
        if (is_string($s2) && $s2 !== '') {
            return $s2;
        }

        return null;
    }
}
