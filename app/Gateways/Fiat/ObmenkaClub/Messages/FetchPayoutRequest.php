<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\ObmenkaClub\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use InvalidArgumentException;

final class FetchPayoutRequest extends AbstractRequest
{
    public function getData(): array
    {
        return [
            'externalId' => $this->requirePayoutTrackingId(
                'externalId / withdrawRecordId обязателен для fetchPayout.'
            ),
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        // пример эндпоинта — подставь реальный rapira endpoint
        $response = $this->callApi('get', '/payments/status/' . $data['externalId']);

        return $this->response = new FetchPayoutResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
