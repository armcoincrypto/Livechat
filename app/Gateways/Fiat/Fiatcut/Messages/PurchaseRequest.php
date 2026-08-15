<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Fiatcut\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;

/**
 * Создание платежа (incoming).
 */
final class PurchaseRequest extends AbstractRequest
{
    public function getData(): array
    {
        // Входные параметры операции
        $this->validate('amount', 'currency', 'transactionId');
        $this->validateConfig('api_token');

        $amount = trim((string) $this->getParameter('amount'));
        if ($amount === '') {
            throw new \InvalidArgumentException('amount пустой.');
        }

        $merchantData = $this->getMerchant();

        // Значения из конфига (merchant options_fields)
        $bankName = ($merchantData->ext_options['bank_name'] ?? 0);
        $typePay  =  ($merchantData->ext_options['type_pay'] ?? 'card');

        return [
            'external_id'          => (string) $this->getTransactionId(),
            'amount'               => (float) $amount,
            'payment_detail_type'  => $typePay,
            'payment_gateway'      => $bankName,
            'merchant_id'          => (string) $this->getApiMerchant(),
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {

        $response = $this->sendRequest('post', '/api/h2h/order', $data);

        return $this->response = new PurchaseResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
