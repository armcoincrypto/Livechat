<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\CryptoCash\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use InvalidArgumentException;

/**
 * Polling входящего платежа через API CryptoCash.
 *
 * Ожидается, что снаружи передадут идентификатор платежа, например:
 * - externalId (из PurchaseResponse::getExternalId())
 *
 * Внутри используем общий helper requireIncomingTrackingId().
 */
final class FetchPaymentRequest extends AbstractRequest
{
    public function getData(): array
    {
        // общий helper для incoming polling (externalId/id/...)
        $trackingId = $this->requireIncomingTrackingId('externalId обязателен для fetchPayment.');

        return [
            'externalId' => (string) $trackingId,
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        // Если CryptoCash реально ждёт POST + body
        $httpResponse = $this->sendRequest('post', '/merchant/api/v1/balance/payments/retrieve', $data);

        // гарантируем массив
        $payload = is_array($httpResponse) ? $httpResponse : [];

        return $this->response = new FetchPaymentResponse(
            request: $this,
            data: $payload,
            query: $data
        );
    }
}
