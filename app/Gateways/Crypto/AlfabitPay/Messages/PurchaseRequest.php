<?php

declare(strict_types=1);

namespace App\Gateways\Crypto\AlfabitPay\Messages;

use iEXPackages\Payments\Core\Contracts\ResponseInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Создание платежа (incoming).
 */
final class PurchaseRequest extends AbstractRequest
{
    public function getData(): array
    {
        $this->validate('amount');
        $this->validateConfig('api_key');

        // контекст
        $task = $this->getTask();
        if (!$task) {
            throw new InvalidArgumentException('Task не привязан к запросу (withTask обязателен).');
        }

        $merchantData = $this->getMerchant();
        $convertTo = ($merchantData->ext_options['convert_to'] ?? '');


        $invoiceAssetCode = (string) ($task->direction_exchange?->currency1?->code_currency?->name ?? '');
        if ($invoiceAssetCode === '') {
            throw new InvalidArgumentException('Не удалось определить invoiceAssetCode (currency1->code_currency->name).');
        }

        if ($convertTo !== '') {
            $invoiceAssetCode = $convertTo;
        }

        return [
            'currencyInCode'     => (string) $this->getMerchantNetworkCode(),
            'comment'            => 'order_' . (string) $this->getTransactionId(),
            'publicComment'      => (string) $this->getDescription(),
            'invoiceAssetCode'   => $invoiceAssetCode,
            'invoiceAmount'      => (string) $this->getAmount(),
            'isAwaitRequisites'  => true,
        ];
    }

    protected function sendData(array $data): ResponseInterface
    {
        $httpResponse = $this->sendRequest('post', '/api/v1/integration/orders/invoice', $data);

        return $this->response = new PurchaseResponse(
            $this,
            is_array($httpResponse) ? $httpResponse : [],
            $data
        );
    }
}
