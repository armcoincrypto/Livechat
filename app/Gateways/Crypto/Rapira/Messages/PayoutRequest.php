<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\Rapira\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class PayoutRequest extends AbstractRequest
{
    public function getData(): array
    {
        // amount обязателен
        $this->validate('amount');

        $task    = $this->getTask();
        $payment = $this->getPayment();

        if (!$task || !$payment) {
            throw new InvalidArgumentException(
                'Для выплаты обязательно привязать Task (withTask) и GatewayPayment (forPayment/withPayment).'
            );
        }

        $address = (string) ($task->to_shot ?? '');
        $coin    = (string) ($task->direction_exchange?->currency2?->code_currency?->name ?? '');
        $chain   = (string) $this->getPaymentNetworkCode(true);

        if ($address === '' || $coin === '' || $chain === '') {
            throw new InvalidArgumentException(
                'Недостаточно данных для выплаты: требуются address (task->to_shot), coin и chain.'
            );
        }

        // network code
        $chain = (string) $this->getPaymentNetworkCode();
        if ($chain === '') {
            throw new InvalidArgumentException('Не удалось определить chain (network code) для выплаты.');
        }

        $extParams = (array) ($payment->ext_options ?? []);

        $payload = [
            'coin'    => $coin,
            'chain'   => $chain,
            'address' => $address,
            'amount'  => (string) $this->getParameter('amount'),
            'nonce'   => $this->getRapiraNonce(),
            'conversionDetails' => [
                'conversionEnabled'   => (bool) ($extParams['conversion_enabled'] ?? false),
                'convertFromCurrency' => (string) ($extParams['convert_from_currency'] ?? ''),
                'enoughCoinPayment'   => (bool) ($extParams['enough_coin_payment'] ?? false),
            ],
        ];

        // memo из доп. полей out
        if ($memo = $this->getCurrencyOutField('outcome_unk')) {
            $payload['memo'] = (string) $memo;
        }

        return $payload;
    }

    protected function sendData(array $data): ResponseInterface
    {
        $response = $this->sendRequest('post', '/open/withdraw/create', $data, 'asJson');

        return $this->response = new PayoutResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
