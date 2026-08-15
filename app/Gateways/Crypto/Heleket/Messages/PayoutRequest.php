<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Heleket\Messages;

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

        $address = (string) ($task->to_shot ?? '');
        if ($address === '') {
            throw new InvalidArgumentException('Адрес выплаты (task->to_shot) пустой.');
        }

        $currency = (string) $this->getCurrency();
        if ($currency === '') {
            throw new InvalidArgumentException('Не удалось определить валюту выплаты.');
        }

        $chain = (string) $this->getPaymentNetworkCode(false);
        $ext   = (array) ($payment->ext_options ?? []);

        $payload = [
            'address'   => $address,
            'amount'    => (string) $this->getAmount(),
            'currency'  => $currency,
            'order_id'  => (string) $this->getTransactionId(),
            'priority'  => $ext['priority'] ?? 'recommended',
            'is_subtract' => ((int)($ext['is_subtract'] ?? 0) === 1),
        ];

        if ($memo = $this->getCurrencyOutField('outcome_unk')) {
            $payload['memo'] = (string) $memo;
        }

        if (!empty($ext['currency_code'])) {
            $payload['from_currency'] = Str::upper((string) $ext['currency_code']);
        }

        if ($chain !== '') {
            $payload['network'] = $chain;
        }

        return $payload;
    }

    protected function sendData(array $data): ResponseInterface
    {
        $response = $this->sendRequest('post', '/v1/payout', $data);

        return $this->response = new PayoutResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
