<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\B2BWallet\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use InvalidArgumentException;

final class FetchPayoutRequest extends AbstractRequest
{
    public function getData(): array
    {
        // externalId — это withdrawRecordId из PayoutResponse
        $externalId = (string) $this->getParameter('externalId');

        if ($externalId === '') {
            throw new InvalidArgumentException('externalId обязателен для fetchPayout.');
        }

        return [
            'id' => $externalId,
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        // пример эндпоинта — подставь реальный rapira endpoint
        $response = $this->sendRequest('get', '/api/v1/transaction', $data);
        return $this->response = new FetchPayoutResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
