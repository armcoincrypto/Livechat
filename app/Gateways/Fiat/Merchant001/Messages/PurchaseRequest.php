<?php

declare(strict_types=1);

namespace App\Gateways\Fiat\Merchant001\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;

/**
 * Создание платежа (incoming).
 */
final class PurchaseRequest extends AbstractRequest
{
    public function getData(): array
    {
        // Пример: минимальная валидация
        // $this->validate('amount', 'currency');

        $methodPay = (string) $this->merchantString('method_pay', 'any_rub_bank');

        return [
            'isPartnerFee' => true,
            'pricing' => [
                'local' => [
                    'amount' => (float) $this->getAmount(),
                    'currency' => $this->getCurrency(),
                ],
            ],
            'selectedProvider' => [
                'method' => $methodPay,
            ],

            'invoiceId' => $this->getTransactionId(),
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        // TODO: замени endpoint на реальный
        $response = $this->sendRequest('post', '/v2/transaction/merchant', $data, 'asJson');



        echo '<pre>';
        print_r($response);
        echo '</pre>';

        exit;
        return $this->response = new PurchaseResponse(
            $this,
            is_array($response) ? $response : [],
            $data
        );
    }
}
