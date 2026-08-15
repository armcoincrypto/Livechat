<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Rapira\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use InvalidArgumentException;

final class FetchPayoutRequest extends AbstractRequest
{
    public function getData(): array
    {
        // externalId — это withdrawRecordId из PayoutResponse
        $externalId = (string) ($this->getParameter('externalId') ?? $this->getParameter('withdrawRecordId') ?? '');

        if ($externalId === '') {
            throw new InvalidArgumentException('externalId (withdrawRecordId) обязателен для fetchPayout.');
        }

        return [
            'withdrawRecordId' => $externalId,
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        // пример эндпоинта — подставь реальный rapira endpoint
        $response = $this->sendRequest('get', '/open/withdraw/crypto/history/' . $data['withdrawRecordId']);

        return $this->response = new FetchPayoutResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
