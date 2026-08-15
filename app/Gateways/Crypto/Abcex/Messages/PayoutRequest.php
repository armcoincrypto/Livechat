<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Abcex\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use iEXPackages\Payments\Core\Traits\PayoutContextTrait;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class PayoutRequest extends AbstractRequest
{
    use PayoutContextTrait;

    public function getData(): array
    {
        $this->validate('amount');

        ['task' => $task, 'payment' => $payment] = $this->requirePaymentContext();

        $walletId = ($payment->ext_options['wallet_unique_id'] ?? '');


        $address = (string) ($task->to_shot ?? '');
        if ($address === '') {
            throw new InvalidArgumentException('Адрес выплаты (task->to_shot) пустой.');
        }

        $chain = (string) $this->getPaymentNetworkCode();
        if ($chain === '') {
            throw new InvalidArgumentException('Не удалось определить chain (network code) для выплаты.');
        }

        return [
            'walletId' => $walletId,
            'networkId' => $chain,
            'volume'  => (string) $this->getAmount(),
            'address' => $address,
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        // Формат по умолчанию asJson, если твой http()->request поддерживает параметр формата — передай
        $response = $this->sendRequest('post', '/api/v1/wallet/create-and-submit-crypto-out', $data);

        return $this->response = new PayoutResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
