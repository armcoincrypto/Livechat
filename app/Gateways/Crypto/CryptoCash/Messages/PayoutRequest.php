<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\CryptoCash\Messages;

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

        // Адрес/счёт (очищенный)
        $address = (string) $this->getCleanPayoutAccount();
        $address = trim($address);

        if ($address === '') {
            throw new InvalidArgumentException('Адрес выплаты (task->to_shot) пустой.');
        }

        // Network code (chain)
        $chain = (string) $this->getPaymentNetworkCode();
        $chain = trim($chain);

        if ($chain === '') {
            throw new InvalidArgumentException('Не удалось определить chain (network code) для выплаты.');
        }

        // Сумма (строкой)
        $amount = trim((string) $this->getParameter('amount'));
        if ($amount === '') {
            throw new InvalidArgumentException('amount пустой.');
        }

        $payload = [
            'currency' => $this->getCurrency(),
            'network'    => $chain,
            'amount'  => $amount,
            'address' => $address,
            'externalId' => (string)'OUT_' .$this->getTransactionId() ?? '0',
        ];

        // Destination tag / memo из доп. полей заявки (out)
        if ($destTag = $this->getCurrencyOutField('outcome_unk')) {
            $payload['memo'] = (string) $destTag;
        }

        return $payload;
    }

    protected function sendData(array $data): ResponseInterface
    {
        $response = $this->sendRequest('post', '/merchant/api/v1/balance/actions/buy/crypto/', $data);

        return $this->response = new PayoutResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
