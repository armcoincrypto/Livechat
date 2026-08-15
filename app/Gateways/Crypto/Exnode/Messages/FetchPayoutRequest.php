<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Exnode\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;

final class FetchPayoutRequest extends AbstractRequest
{
    public function getData(): array
    {
        return [
            'tracker_id' => $this->requirePayoutTrackingId(
                'externalId / withdrawRecordId обязателен для fetchPayout.'
            ),
        ];
    }


    protected function sendData(array $data): ResponseInterface
    {
        // пример эндпоинта — подставь реальный rapira endpoint
        $response = $this->sendRequest('post', '/api/transaction/get', $data);

        return $this->response = new FetchPayoutResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
