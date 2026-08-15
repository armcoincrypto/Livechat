<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Firekassa\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use Illuminate\Support\Str;

/**
 * Проверка поступления средств через API Rapira (polling).
 *
 * Входные параметры предполагаются такими:
 *  - externalId  — ID депозита/платежа в Rapira (из PurchaseResponse::getExternalId())
 *  - transactionId (опционально) — твой внутренний ID (для логов/связки с Task)
 */
final class FetchPaymentRequest extends AbstractRequest
{
    /**
     * Сборка payload для Rapira API.
     *
     * Мы ожидаем, что:
     *  - externalId передаётся снаружи, или
     *  - его можно взять из параметров Request.
     */
    public function getData(): array
    {
        $trackingId = $this->requireIncomingTrackingId();

        return [
            'id' => (string) $trackingId
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $httpResponse = $this->sendRequest('get', '/api/v2/transactions/' . $data['id']);

        return $this->response = new FetchPaymentResponse(
            request: $this,
            data: (array) $httpResponse,
            query: $data
        );
    }
}
