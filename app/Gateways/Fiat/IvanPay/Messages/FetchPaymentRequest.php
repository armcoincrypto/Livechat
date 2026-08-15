<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\IvanPay\Messages;

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
        $trackingId = $this->requireIncomingTrackingId();

        return [
            'payment_id' => (string) $trackingId
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {

        $httpResponse = $this->sendRequest('post', '/checkPayment', $data);

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
