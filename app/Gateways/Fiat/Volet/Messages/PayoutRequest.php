<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Volet\Messages;

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

        // Контекст
        $task    = $this->getTask();
        $payment = $this->getPayment();

        if (!$task || !$payment) {
            throw new InvalidArgumentException(
                'Для выплаты обязательно привязать Task (withTask) и GatewayPayment (forPayment/withPayment).'
            );
        }

        $address = $this->getCleanPayoutAccount();
        if ($address === '') {
            throw new InvalidArgumentException('Адрес выплаты (task->to_shot) пустой.');
        }

        // amount/currency
        $amount = trim((string) $this->getParameter('amount'));
        if ($amount === '') {
            throw new InvalidArgumentException('amount пустой.');
        }

        $emailReceived = $this->getCurrencyOutField('outcome_email_received', '') ?? '';


        return [
            'amount' => (float) $amount,
            'account' => $address,
            'currency' => str_replace('RUB', 'RUR', $this->getCurrency()),
            'note' => $this->getPayoutComment(),
            'email' => $emailReceived ?? ''
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $payment = $this->getPayment();

        $typeTransaction = (string) (($payment->ext_options['type_transaction'] ?? '') ?: '');

        if($typeTransaction == 'wallet') {
            $result = $this->soapCall('sendMoney', [
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'email' => '',
                'walletId' => $data['account'],
                'note' => $data['note'] ?? '',
                'savePaymentTemplate' => false,
            ]);
        } else {
            $result = $this->soapCall('sendMoneyToEmail', [
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'email' => $data['email'],
                'note' => $data['note'] ?? ''
            ]);
        }

        return $this->response = new PayoutResponse(
            $this,
            is_array($result) ? $result : [],
            $data
        );
    }
}
