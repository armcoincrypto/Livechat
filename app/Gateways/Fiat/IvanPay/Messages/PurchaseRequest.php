<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\IvanPay\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;

/**
 * Создание платежа (incoming).
 */
final class PurchaseRequest extends AbstractRequest
{
    public function getData(): array
    {
        // Пример: минимальная валидация
        $this->validate('amount', 'currency');
        //
        $fromShot = $this->getTask()->from_shot ?? '';

        $senderFullname = $this->getCurrencyInField('sender_fullname', 'Default Name');


        return [
            'currency' => $this->merchantString('bank_name', ''),
            'amount' => (float) $this->getAmount(),
            'card_number' => $fromShot,
            'card_holder' => $senderFullname,
            'ext_id' => (string)$this->getTransactionId(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'email' => $this->getTask()->email ?? '',
            'photo_verification' => 'yes - from '. (string)$this->getTransactionId()
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $typePay = $this->merchantString('type_pay', 'card');
        $path = ($typePay == 'card') ? '/createIncomingPayFast' : '/createIncomingSbpPayFast';

        $response = $this->sendRequest('post', $path, $data, 'asJson');

        return $this->response = new PurchaseResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
