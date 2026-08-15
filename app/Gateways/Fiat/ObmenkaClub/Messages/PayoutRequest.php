<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\ObmenkaClub\Messages;

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

        // Контекст
        $task    = $this->getTask();
        $payment = $this->getPayment();

        if (!$task || !$payment) {
            throw new InvalidArgumentException(
                'Для выплаты обязательно привязать Task (withTask) и GatewayPayment (forPayment/withPayment).'
            );
        }

        // Адрес/карта/счёт
        $account = $this->getCleanPayoutAccount();
        if ($account === '') {
            throw new InvalidArgumentException('Адрес выплаты (task->to_shot) пустой.');
        }


        // amount/currency
        $amount = trim((string) $this->getParameter('amount'));
        if ($amount === '') {
            throw new InvalidArgumentException('amount пустой.');
        }

        return [
            'token' => (string) $this->getTask()->id,
            'amount' => $amount,
            'currency' => (string)$this->getCurrency(),
            'card' => (string)$account
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $response = $this->callApi('post', '/payments/create', $data);

        return $this->response = new PayoutResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
