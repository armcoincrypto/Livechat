<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\AlfabitPay\Messages;

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


        $address = (string) $this->getCleanPayoutAddress();
        if ($address === '') {
            throw new InvalidArgumentException('Адрес выплаты пустой.');
        }

        $toCurrencyCode = (string) $this->getPaymentNetworkCode();
        if ($toCurrencyCode === '') {
            throw new InvalidArgumentException('Не удалось определить chain (network code) для выплаты.');
        }



        $response = [
            'walletId' => $walletId,
            'toCurrencyCode' => $toCurrencyCode,
            'volume'  => (string) $this->getAmount(),
            'recipient' => $address,
            'comment' => $this->getPayoutComment()
        ];


        // Добавляем memo / tag, если он есть
        if ($memo = $this->getCurrencyOutField('outcome_unk')) {
            $response['requisitesMemoTag'] = $memo;
        }

        return $response;
    }

    protected function sendData(array $data): ResponseInterface
    {
        $response = $this->sendRequest('post', '/api/v1/integration/orders/withdraw', $data);

        return $this->response = new PayoutResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
