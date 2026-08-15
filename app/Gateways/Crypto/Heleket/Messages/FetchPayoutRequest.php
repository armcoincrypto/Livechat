<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Heleket\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;

final class FetchPayoutRequest extends AbstractRequest
{
    public function getData(): array
    {
        return [
            'uuid' => $this->requirePayoutTrackingId(
                'externalId / withdrawRecordId обязателен для fetchPayout.'
            ),
        ];
    }


    protected function sendData(array $data): ResponseInterface
    {
        $response = $this->sendRequest('post', '/v1/payout/info', $data);

        return $this->response = new FetchPayoutResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
