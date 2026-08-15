<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Exnode\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use iEXPackages\Payments\Core\Traits\PayoutContextTrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PayoutRequest extends AbstractRequest
{
    use PayoutContextTrait;

    public function getData(): array
    {
        $this->validate('amount');

        ['task' => $task, 'payment' => $payment] = $this->requirePaymentContext();

        $address = trim((string) ($task->to_shot ?? ''));
        if ($address === '') {
            throw new InvalidArgumentException('Адрес выплаты (task->to_shot) пустой.');
        }

        $token = trim((string) $this->getPaymentNetworkCode());
        if ($token === '') {
            throw new InvalidArgumentException('Не удалось определить token (network code) для выплаты.');
        }

        $payload = [
            'receiver'   => $address,
            'amount'    => (float) $this->getAmount(),
            'token'                   => $token,
            'transaction_description' => (string) $this->getPayoutComment(),
            'client_transaction_id'   => (string) $this->getTransactionId() . '_out',
        ];

        if ($memo = $this->getCurrencyOutField('outcome_unk')) {
            $payload['dest_tag'] = (string) $memo;
        }

        return $payload;
    }

    protected function sendData(array $data): ResponseInterface
    {
        $response = $this->sendRequest('post', '/api/transaction/create/out', $data);

        return $this->response = new PayoutResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
