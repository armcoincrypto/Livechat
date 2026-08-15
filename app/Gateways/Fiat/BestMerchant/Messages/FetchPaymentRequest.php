<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\BestMerchant\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;

/**
 * Проверка поступления средств через API (polling).
 *
 * Входные параметры предполагаются такими:
 *  - externalId  — ID депозита/платежа  (из PurchaseResponse::getExternalId())
 *  - transactionId (опционально) — твой внутренний ID (для логов/связки с Task)
 */
final class FetchPaymentRequest extends AbstractRequest
{
    /**
     * Сборка payload для API.
     *
     * Мы ожидаем, что:
     *  - externalId передаётся снаружи, или
     *  - его можно взять из параметров Request.
     */
    public function getData(): array
    {
        // обязательный параметр: externalId (ID депозита)
        $externalId = $this->getParameter('externalId');
        // минимальная проверка
        if ($externalId === null || $externalId === '') {
            // можно сделать InvalidRequestException, если он у тебя есть
            throw new \InvalidArgumentException('externalId (или id) обязателен для checkPayment');
        }

        return [
            'id' => (string) $externalId
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $httpResponse = $this->sendRequest('get', '/api/smart-orders/' . $data['id']);

        if (empty($httpResponse)) {
            throw new \InvalidArgumentException('Транзакция не найдена');
        }

        return $this->response = new FetchPaymentResponse(
            request: $this,
            data: (array) $httpResponse,
            query: $data
        );
    }
}
