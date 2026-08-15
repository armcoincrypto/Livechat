<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\IvanPay\Messages;

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

        $bankName = $this->payString('bank_name', '');

        return [
            'currency' => $bankName,
            'amount' => (float) $amount,
            'card_number' => $account,
            'email' => $this->getTask()->email ?? '',
            'user_agent' => request()->userAgent(),
            'ext_id' => $this->getTransactionId(),
            'ip' => $this->getTask()->ip
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $typePay = $this->payString('type_pay', 'card');

        if ($typePay === 'card') {
            $response = $this->sendRequest('post', '/createOutgoingPay', $data);
        } else {
            $sbpBankCode = (string) $this->getCurrencyOutField('recipient_fullname');

            $response = $this->sendRequest('post', '/createOutgoingSbpPay', [
                ...$data,
                'sbp_bank_code' => $sbpBankCode,
            ]);
        }

        return $this->response = new PayoutResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
