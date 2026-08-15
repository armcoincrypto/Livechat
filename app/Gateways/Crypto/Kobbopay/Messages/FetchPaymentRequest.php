<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Kobbopay\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;

final class FetchPaymentRequest extends AbstractRequest
{
    public function getData(): array
    {
        $trackingId = $this->requireIncomingTrackingId();

        return [
            'tracker_id' => (string) $trackingId,
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $txOrder = $this->sendRequest('get', '/api/crypto/invoice/get/', [
            'tracker_id' => $data['tracker_id'],
        ]);

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
