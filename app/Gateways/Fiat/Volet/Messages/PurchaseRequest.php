<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Volet\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use InvalidArgumentException;

final class PurchaseRequest extends AbstractRequest
{
    /**
     * SCI purchase: формирует POST-поля для формы.
     *
     * Обязательные входные параметры (query):
     * - amount
     * - currency
     * - transactionId
     *
     * Берёт конфиг из merchant fields:
     * - sci_account_email
     * - sci_name
     * - sci_password
     */
    public function getData(): array
    {
        $this->validate('amount', 'currency', 'transactionId');

        // ВАЖНО: эти методы будут из GeneratedMerchantInputs (или вручную)
        $accountEmail = (string) ($this->getSciAccountEmail() ?: '');
        $sciName      = (string) ($this->getSciName() ?: '');
        $sciPassword  = (string) ($this->getSciPassword() ?: '');

        if ($accountEmail === '' || $sciName === '' || $sciPassword === '') {
            throw new InvalidArgumentException('Volet: отсутствуют параметры SCI (sci_account_email/sci_name/sci_password).');
        }

        $amount        = (string) $this->getAmount();
        $currency      = (string) $this->getCurrency();
        $transactionId = (string) $this->getTransactionId();
        $description   = (string) $this->getDescription();

        // URLs (если ты их передаёшь в purchase([...]) как notifyUrl/returnUrl/cancelUrl)
        $returnUrl = (string) $this->getParameter('returnUrl', '');
        $cancelUrl = (string) $this->getParameter('cancelUrl', '');
        $notifyUrl = (string) $this->getParameter('notifyUrl', ''); // Volet status_url

        $data = [
            'ac_account_email'        => $accountEmail,
            'ac_sci_name'             => $sciName,
            'ac_amount'               => $amount,
            'ac_currency'             => $currency,
            'ac_order_id'             => $transactionId,
            'ac_comments'             => $description,

            // Optional fields
            'ac_success_url'          => $returnUrl,
            'ac_success_url_method'   => 'GET',
            'ac_fail_url'             => $cancelUrl,
            'ac_fail_url_method'      => 'GET',

            // callback (IPN/status)
            'ac_status_url'           => $notifyUrl,
            'ac_status_url_method'    => 'POST',
        ];

        // Убираем пустые optional поля, чтобы не слать мусор
        $data = array_filter($data, static fn($v) => $v !== null && $v !== '');

        // Подпись (как у тебя было):
        // sha256("email:sciName:amount:currency:sciPassword:orderId")
        $data['ac_sign'] = hash('sha256', implode(':', [
            $accountEmail,
            $sciName,
            $amount,
            $currency,
            $sciPassword,
            $transactionId,
        ]));

        return $data;
    }

    /**
     * SCI purchase не делает HTTP запрос — возвращает redirect-response.
     */
    protected function sendData(array $data): ResponseInterface
    {
        return $this->response = new PurchaseResponse(
            request: $this,
            data: $data,
            query: $this->getParameters()
        );
    }
}
