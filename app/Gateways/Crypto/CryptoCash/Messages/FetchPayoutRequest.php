<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\CryptoCash\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use InvalidArgumentException;

final class FetchPayoutRequest extends AbstractRequest
{
    public function getData(): array
    {
        return [
            'externalId' => (string) $this->requirePayoutTrackingId(
                'externalId / withdrawRecordId обязателен для fetchPayout.'
            ),
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        // пример эндпоинта — подставь реальный rapira endpoint
        $response = $this->sendRequest('post', '/merchant/api/v1/balance/payments/retrieve/', $data);

        return $this->response = new FetchPayoutResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
