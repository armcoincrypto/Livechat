<?php
declare(strict_types=1);

namespace iEXPackages\Payment\Gateways\Volet\Message;

use iEXPackages\Payment\Engines\Message\ResponseInterface;
use iEXPackages\Payment\Exception\InvalidRequestException;
use Illuminate\Support\Facades\Log;

class PurchaseRequest extends AbstractRequest
{
    /**
     * Получить массив необработанных данных для этого сообщения.
     * Формат этого варьируется от шлюза к шлюзу,
     * но обычно это либо ассоциативный массив, либо SimpleXMLElement.
     *
     * @throws InvalidRequestException
     */
    public function getData(): array
    {
        $this->validate(
            'amount', 'currency', 'transactionId'
        );

        $response =  [
            'ac_account_email' => $this->getSciAccountEmail(),
            'ac_sci_name' => $this->getSciName(),
            'ac_amount' => $this->getAmount(),
            'ac_currency' => $this->getCurrency(),
            'ac_order_id' => $this->getTransactionId(),
            'ac_comments' => $this->getDescription(),
            'ac_success_url' => $this->getReturnUrl(),
            'ac_success_url_method' => 'GET',
            'ac_fail_url' => $this->getCancelUrl(),
            'ac_fail_url_method' => 'GET',
        ];

        $response['ac_sign'] = hash('sha256', implode(':', [
            $response['ac_account_email'],
            $response['ac_sci_name'],
            $response['ac_amount'],
            $response['ac_currency'],
            $this->getSCIPassword(),
            $response['ac_order_id'],
        ]));

        return $response;
    }

    /**
     * Отправить запрос с указанными данными
     */
    public function sendData(array $data): ResponseInterface
    {
        return $this->response = new PurchaseResponse($this, $data);
    }
}
