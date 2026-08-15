<?php

namespace App\Gateways\Crypto\Rapira\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use iEXPackages\Payments\Core\Engine\HookResponse;

final class HookOrderRejectedRequest extends AbstractRequest
{
    public function getData(): array
    {
        return [
            'address' => $this->getTask()->transfer_to_account ?? '',
            'nonce' => request()->httpHost() . '_' . $this->getTask()->id,
            'status' => 'delete'
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $this->sendRequest('post', '/open/deposit_address/use/stop', $data, 'asJson');
        $payload = [
            'ok'        => true,
            'pending'   => false,
            'cancelled' => false,
            'message'   => 'Проверка пройдена'
        ];

        return $this->response = new HookResponse(
            request: $this,
            data: $payload,
            query: $data
        );
    }
}
