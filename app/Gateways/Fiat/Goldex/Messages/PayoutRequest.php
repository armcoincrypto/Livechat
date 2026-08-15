<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Goldex\Messages;

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

        $task    = $this->getTask();
        $payment = $this->getPayment();

        if (!$task || !$payment) {
            throw new InvalidArgumentException(
                'Для выплаты обязательно привязать Task (withTask) и GatewayPayment (forPayment/withPayment).'
            );
        }

        // 1) Куда выплачиваем
        $account = (string) ($this->getCleanPayoutAccount() ?? '');
        $account = trim($account);

        if ($account === '') {
            throw new InvalidArgumentException('Адрес выплаты (task->to_shot) пустой.');
        }

        // 2) Сумма
        $amount = trim((string) $this->getParameter('amount'));
        if ($amount === '' || !is_numeric($amount)) {
            throw new InvalidArgumentException('amount пустой или некорректный.');
        }

        // 3) exchangeRateId обязателен (это твой bank_name из pay ext_options)
        $exchangeRateId = $this->payString('bank_name', '');
        if ($exchangeRateId === '') {
            throw new InvalidArgumentException('Не выбран метод выплаты (bank_name / exchangeRateId).');
        }

        $email = (string) ($task->user?->email ?? $task->email ?? '');
        if ($email === '') {
            // как минимум не валим, но лучше иметь email
            $email = 'no-email';
        }


        $params = [
            'exchangeRateId' => $exchangeRateId,
            'amount'         => (float) $amount,
            'cardNumber'     => $account,
            'requestId'      => (string) $this->getTransactionId((string) $task->id),
            'email'          => $email,
        ];

        // 4) Доп. поля (как в старом buy_additional_fields)
        $recipientFullname = $this->getCurrencyOutField('recipient_fullname');
        if (is_string($recipientFullname) && trim($recipientFullname) !== '') {
            $params['cardHolder'] = trim($recipientFullname);
        }

        $externalBankName = $this->getCurrencyOutField('outcome_bankname');
        if (is_string($externalBankName) && trim($externalBankName) !== '') {
            $params['externalBankName'] = trim($externalBankName);
        }


        return $params;
    }

    protected function sendData(array $data): ResponseInterface
    {
        $response = $this->sendRequest('post', '/api/v3/request/', $data);

        return $this->response = new PayoutResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
