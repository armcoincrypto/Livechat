<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Bydex\Messages;

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
        $address = $this->getCleanPayoutAccount();
        if ($address === '') {
            throw new InvalidArgumentException('Адрес выплаты (task->to_shot) пустой.');
        }

        // Доп. поля из заявки (out)
        $recipientFullname = $this->getCurrencyOutField('recipient_fullname'); // как у тебя было
        $phone             = $this->getCurrencyOutField('outcome_phone');
        $telegramAccount   = $this->getCurrencyOutField('outcome_telegram');

        $bankName = $this->getCurrencyOutField('outcome_bank_name')
            ?? $this->getCurrencyOutField('outcome_bank');


        // Способ выплаты: Card / SBP (из ext_options или из inputs.pay)
        $methodPay = (string) (($payment->ext_options['method_pay'] ?? '') ?: '');
        if ($methodPay === '') {
            // fallback: из Vault-конфига, если ты хранишь method_pay там
            $methodPay = (string) ($this->configString('method_pay') ?: 'Card');
        }

        // tg_client: старый код выбирал tg если есть
        $contact = $telegramAccount !== null && $telegramAccount !== '' ? $telegramAccount : null;

        // order_id — лучше использовать transactionId (с fallback на task->id)
        $orderId = (string) ($this->getTransactionId() ?? $task->id);

        // email/ip
        $email = (string) ($task->user?->email ?? '');
        $ip    = (string) ($task->ip ?? '');

        // amount/currency
        $amount = trim((string) $this->getParameter('amount'));
        if ($amount === '') {
            throw new InvalidArgumentException('amount пустой.');
        }

        // Валюта: в старом было RUB
        $currency = 'RUB';

        return [
            'order_id'     => $orderId,
            'ext_txn'      => $methodPay,
            'ext_date'     => Carbon::now()->format('c'),
            'amount'       => $amount,
            'currency'     => $currency,
            'callbackUrl'  => config('app.url'),
            'ip'           => $ip,
            'user_agent'   => '-',

            'phone_number' => $phone ?? '',
            'email'        => $email,

            // куда платить
            'account'      => (string) $address,
            'card_number'  => (string) $address,

            // ФИО (старый код заполнял last_name fullname)
            'last_name'    => $recipientFullname ?? '',
            'first_name'   => '-',
            'middle_name'  => '-',

            'tg_client'    => $contact,

            // тип выплаты
            'type'         => $methodPay,

            // банк
            'bankname'     => $bankName ?? 'AllBanks',
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $response = $this->sendRequest('post', '/api/v1/paymerchants/CreatePay', $data, 'asJson');

        return $this->response = new PayoutResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
