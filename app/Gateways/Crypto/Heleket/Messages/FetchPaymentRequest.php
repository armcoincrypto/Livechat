<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Heleket\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

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
            'uuid' => (string) $trackingId,
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $httpResponse = $this->sendRequest('post', '/v1/payment/info', $data);

        return $this->response = new FetchPaymentResponse(
            request: $this,
            data: (array) $httpResponse,
            query: $data
        );
    }

    /**
     * Helper для выполнения запросов к API.
     *
     * @throws \Throwable
     */
    protected function findTransaction(array $data = []): array
    {
        $response = $this->sendRequest('get', '/api/v1/history', $data);

        return Arr::first($response['history']) ?? [];
    }
}
