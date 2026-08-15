<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Exnode\Messages;

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
            'tracker_id' => (string) $trackingId,
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $txOrder = $this->sendRequest('get', '/api/crypto/invoice/get/?tracker_id=' . $data['tracker_id']);

        if (!isset($txOrder['transaction_tracker_id'])) {
            return $this->response = new FetchPaymentResponse(
                request: $this,
                data: ['transaction' => null],
                query: $data
            );
        }

        $httpResponse = $this->sendRequest('post', '/api/transaction/get', [
            'tracker_id' => $txOrder['transaction_tracker_id'],
        ]);


        return $this->response = new FetchPaymentResponse(
            request: $this,
            data: (array) $httpResponse,
            query: $data
        );
    }
}
